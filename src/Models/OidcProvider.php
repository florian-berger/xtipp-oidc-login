<?php
namespace FlorianBerger\Oidc\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OidcProvider
 */
class OidcProvider extends Model {
    /** @var string */
    protected $table = 'plugin_oidc_providers';

    /** @var string[] $fillable */
    protected $fillable = [
        'name',
        'slug',
        'button_label',
        'button_color',
        'icon',
        'issuer',
        'client_id',
        'client_secret',
        'scopes',
        'extra_authorize_params',
        'active',
    ];

    /**
     * @return string[]
     */
    protected function casts(): array {
        return [
            'client_secret'          => 'encrypted',
            'extra_authorize_params' => 'array',
            'active'                 => 'boolean',
        ];
    }

    /**
     * Get all identities linked to this provider
     *
     * @return HasMany
     */
    public function identities(): HasMany {
        return $this->hasMany(OidcIdentity::class, 'oidc_provider_id');
    }

    /**
     * Scope to only active providers
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('active', true);
    }

    /**
     * The name to show in texts, e.g. "verknüpft mit <name>"
     *
     * @return string
     */
    public function displayName(): string {
        return $this->name;
    }

    /**
     * Placeholder in the label of the button, replaced with the name (e.g. "Weiter mit $Name")
     */
    public const BUTTON_LABEL_NAME_PLACEHOLDER = '$Name';

    /**
     * The text of the login button: the own label with "$Name" replaced by the name, otherwise "Anmelden mit <name>"
     *
     * @return string
     */
    public function buttonText(): string {
        $label = trim((string) $this->button_label);

        if ($label === '') {
            return trans('oidc::ui.login_with', ['provider' => $this->name]);
        }

        return str_replace(self::BUTTON_LABEL_NAME_PLACEHOLDER, (string) $this->name, $label);
    }

    /**
     * Parse the "scopes" field into single scopes, separated by spaces or commas
     *
     * @return string[]
     */
    public function scopesArray(): array {
        return array_values(array_filter(preg_split('/[\s,]+/', trim((string) $this->scopes))));
    }
}
