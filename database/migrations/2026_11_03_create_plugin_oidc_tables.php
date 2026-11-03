<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('plugin_oidc_providers')) {
            Schema::create('plugin_oidc_providers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('button_label')->nullable();
                $table->string('button_color')->nullable();
                $table->string('icon')->nullable();
                $table->string('issuer');
                $table->string('client_id');
                $table->text('client_secret');
                $table->string('scopes')->default('openid email profile');
                $table->text('extra_authorize_params')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plugin_oidc_identities')) {
            Schema::create('plugin_oidc_identities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('oidc_provider_id');
                $table->string('subject');
                $table->string('email')->nullable();
                $table->json('raw_claims')->nullable();
                $table->dateTime('last_login_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('oidc_provider_id');
                $table->unique(['oidc_provider_id', 'subject']);
                $table->unique(['user_id', 'oidc_provider_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_oidc_identities');
        Schema::dropIfExists('plugin_oidc_providers');
    }
};
