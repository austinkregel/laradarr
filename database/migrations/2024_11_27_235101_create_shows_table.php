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
        Schema::create('shows', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sonarr_id')->nullable();

            $table->string('name');
            $table->json('aliases')->nullable();
            $table->string('slug')->nullable();
            $table->text('description')->nullable();

            $table->integer('release_year');
            $table->integer('seasons')->default(1);
            $table->integer('episodes')->default(12);

            $table->string('type')->default('anime');

            $table->string('path', 2048)->nullable();

            $table->string('poster_image', 2048)->nullable();
            $table->string('banner_image', 2048)->nullable();
            $table->string('logo_image', 2048)->nullable();
            $table->unsignedBigInteger('size_on_disk')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};
