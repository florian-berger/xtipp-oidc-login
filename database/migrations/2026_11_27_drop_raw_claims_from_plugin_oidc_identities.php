<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The claims of the ID token are no longer stored (data minimisation), this also deletes the ones of existing
 * installations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('plugin_oidc_identities', 'raw_claims')) {
            Schema::table('plugin_oidc_identities', function (Blueprint $table) {
                $table->dropColumn('raw_claims');
            });
        }
    }

    /**
     * Reverse the migrations. The column comes back empty, the deleted claims can not be restored.
     */
    public function down(): void
    {
        if (Schema::hasTable('plugin_oidc_identities') && !Schema::hasColumn('plugin_oidc_identities', 'raw_claims')) {
            Schema::table('plugin_oidc_identities', function (Blueprint $table) {
                $table->json('raw_claims')->nullable()->after('email');
            });
        }
    }
};
