<?php
namespace FlorianBerger\Oidc\Services;

use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Templates for new providers, read from "resources/provider-templates.json"
 *
 * A template holds the values that are the same for every installation (issuer, scopes, appearance, ...). Client-ID and
 * Client-Secret are never part of it. If the file is missing or invalid, the error is logged and no template is
 * returned, so the create page keeps working without the dropdown.
 */
class ProviderTemplates {
    /**
     * Optional text fields of a template, in addition to the required "key" and "name"
     */
    private const OPTIONAL_FIELDS = ['slug', 'issuer', 'scopes', 'extra_authorize_params', 'button_label', 'button_color', 'icon', 'console_url'];

    /**
     * @var array<int, array<string, string|null>>|null
     */
    private ?array $templates = null;

    /**
     * @param string|null $path Path of the JSON file, the file of the plugin by default
     */
    public function __construct(private readonly ?string $path = null) {
    }

    /**
     * All valid templates, in the order of the file
     *
     * @return array<int, array<string, string|null>>
     */
    public function all(): array {
        return $this->templates ??= $this->load();
    }

    /**
     * Read and check the file
     *
     * @return array<int, array<string, string|null>>
     */
    private function load(): array {
        $path = $this->path ?? __DIR__ . '/../../resources/provider-templates.json';

        if (!is_file($path) || !is_readable($path)) {
            Log::error('OIDC: the file with the provider templates is missing or not readable.', ['path' => $path]);

            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error('OIDC: the file with the provider templates is not valid JSON.', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }

        if (!is_array($data) || !array_is_list($data)) {
            Log::error('OIDC: the file with the provider templates must contain a JSON array.', ['path' => $path]);

            return [];
        }

        $templates = [];
        $keys      = [];
        foreach ($data as $index => $entry) {
            $template = $this->normalize($entry);

            if ($template === null) {
                Log::error('OIDC: a provider template is invalid and is skipped.', ['path' => $path, 'index' => $index]);
                continue;
            }

            if (isset($keys[$template['key']])) {
                Log::error('OIDC: the key of a provider template is used twice, the later one is skipped.', ['path' => $path, 'key' => $template['key']]);
                continue;
            }

            $keys[$template['key']] = true;
            $templates[]            = $template;
        }

        return $templates;
    }

    /**
     * Check one entry and bring it into a fixed shape
     *
     * @param mixed $entry
     *
     * @return array<string, string|null>|null Null if the entry is invalid
     */
    private function normalize(mixed $entry): ?array {
        if (!is_array($entry)) {
            return null;
        }

        $key  = $entry['key'] ?? null;
        $name = $entry['name'] ?? null;
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $key) || !is_string($name) || trim($name) === '') {
            return null;
        }

        $template = ['key' => $key, 'name' => trim($name)];

        foreach (self::OPTIONAL_FIELDS as $field) {
            $value = $entry[$field] ?? null;

            // The extra parameters may also be written as a JSON object instead of a string
            if ($field === 'extra_authorize_params' && is_array($value)) {
                $value = $value === [] ? null : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            if ($value !== null && !is_string($value)) {
                return null;
            }

            $template[$field] = ($value === null || trim($value) === '') ? null : trim($value);
        }

        // Shown as a link on the page, so only real https addresses
        if ($template['console_url'] !== null
            && (!str_starts_with($template['console_url'], 'https://') || filter_var($template['console_url'], FILTER_VALIDATE_URL) === false)) {
            return null;
        }

        return $template;
    }
}
