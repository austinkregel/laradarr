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
        Schema::table('movies', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->integer('release_year')->nullable()->after('description');
            $table->dateTime('released_at')->nullable()->after('release_year');
            
            $table->string('poster_image', 2048)->nullable()->after('path');
            $table->string('banner_image', 2048)->nullable()->after('poster_image');
            $table->string('logo_image', 2048)->nullable()->after('banner_image');
            $table->unsignedBigInteger('size_on_disk')->default(0)->after('logo_image');
            
            $table->unsignedBigInteger('tmdb_id')->nullable()->after('imdb_id');
            $table->unsignedBigInteger('tvdb_id')->nullable()->after('tmdb_id');
            $table->unsignedBigInteger('trakt_id')->nullable()->after('tvdb_id');
            $table->string('plex_id')->nullable()->after('trakt_id');
            
            $table->decimal('imdb_rating', 3, 1)->nullable()->after('plex_id');
            $table->decimal('tmdb_rating', 3, 1)->nullable()->after('imdb_rating');
            $table->decimal('trakt_rating', 3, 1)->nullable()->after('tmdb_rating');
            $table->decimal('community_rating', 3, 1)->nullable()->after('trakt_rating');
            
            $table->json('genres')->nullable()->after('community_rating');
            $table->json('available_dub_languages')->nullable()->after('genres');
            
            $table->index('slug');
            $table->index('tmdb_id');
            $table->index('trakt_id');
            $table->index('imdb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'description',
                'release_year',
                'released_at',
                'poster_image',
                'banner_image',
                'logo_image',
                'size_on_disk',
                'tmdb_id',
                'tvdb_id',
                'trakt_id',
                'plex_id',
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
