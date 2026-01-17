<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discoverable_movies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tmdb_id')->nullable();
            $table->unsignedBigInteger('trakt_id')->nullable();
            $table->string('imdb_id')->nullable();

            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();

            $table->integer('release_year')->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedInteger('runtime')->nullable();

            $table->string('poster_image', 2048)->nullable();
            $table->string('backdrop_image', 2048)->nullable();

            $table->json('genres')->nullable();
            $table->json('origin_countries')->nullable();

            $table->decimal('vote_average', 4, 2)->nullable();
            $table->unsignedInteger('vote_count')->nullable();
            $table->decimal('popularity', 10, 3)->nullable();

            $table->string('status')->nullable();
            $table->string('source')->default('tmdb');

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('tmdb_id');
            $table->unique('trakt_id');
            $table->index('imdb_id');
            $table->index(['release_year', 'release_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discoverable_movies');
    }
};

