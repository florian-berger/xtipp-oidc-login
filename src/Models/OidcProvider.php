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
     * The name to show on buttons
     *
     * @return string
     */
    public function displayName(): string {
        return $this->button_label ?: $this->name;
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
