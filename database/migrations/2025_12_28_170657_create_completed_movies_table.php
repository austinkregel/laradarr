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
        Schema::create('completed_movies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Movie::class);
            $table->foreignIdFor(\App\Models\User::class);
            $table->dateTime('completed_at');
            $table->timestamps();

            $table->unique(['movie_id', 'user_id']);
            $table->index(['user_id', 'movie_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('completed_movies');
    }
};
