<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();

            // e.g. trakt, tmdb, sonarr
            $table->string('service')->index();

            // e.g. access_token, refresh_token, api_key, bearer_token
            $table->string('key')->index();

            // Encrypted at rest via model cast
            $table->text('value')->nullable();

            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['service', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};







