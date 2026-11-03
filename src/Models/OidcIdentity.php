<?php
namespace FlorianBerger\Oidc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OidcIdentity
 */
class OidcIdentity extends Model {
    /** @var string */
    protected $table = 'plugin_oidc_identities';

    /** @var string[] $fillable */
    protected $fillable = [
        'user_id',
        'oidc_provider_id',
        'subject',
        'email',
        'raw_claims',
        'last_login_at',
    ];

    /**
     * @return string[]
     */
    protected function casts(): array {
        return [
            'raw_claims'    => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the user this identity belongs to
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the provider this identity was issued by
     *
     * @return BelongsTo
     */
    public function provider(): BelongsTo {
        return $this->belongsTo(OidcProvider::class, 'oidc_provider_id');
    }
}
