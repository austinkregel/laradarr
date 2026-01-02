<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->decimal('imdb_rating', 3, 1)->nullable()->after('imdb_id');
            $table->decimal('tmdb_rating', 3, 1)->nullable()->after('imdb_rating');
            $table->decimal('trakt_rating', 3, 1)->nullable()->after('tmdb_rating');
            $table->decimal('community_rating', 3, 1)->nullable()->after('trakt_rating');

            $table->json('genres')->nullable()->after('community_rating');
            $table->json('available_dub_languages')->nullable()->after('genres');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn([
                'imdb_rating',
                'tmdb_rating',
                'trakt_rating',
                'community_rating',
                'genres',
                'available_dub_languages',
            ]);
        });
    }
};





