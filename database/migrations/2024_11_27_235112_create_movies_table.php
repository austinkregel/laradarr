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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radarr_id');

            $table->string('name');

            $table->integer('runtime');
            $table->unsignedBigInteger('movie_file_id');

            $table->boolean('is_available')->default(false);

            $table->json('aliases')->nullable();
            $table->dateTime('in_cinemas')->nullable();

            $table->string('imdb_id')->nullable();
            $table->dateTime('added_at')->nullable();

            $table->string('path', 2048)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
