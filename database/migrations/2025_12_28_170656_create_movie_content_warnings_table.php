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
        Schema::create('movie_content_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignId('content_warning_id')->constrained('content_warnings')->cascadeOnDelete();
            $table->string('severity')->default('mild')->index(); // mild|moderate|strong
            $table->timestamps();

            $table->unique(['movie_id', 'content_warning_id']);
            $table->index(['content_warning_id', 'movie_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_content_warnings');
    }
};
