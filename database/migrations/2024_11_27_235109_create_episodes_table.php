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
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(\App\Models\Season::class)->index();

            $table->integer('sonarr_episode_id')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            $table->integer('episode_number')-> nullable();
            $table->boolean('has_file')->default(false);
            $table->integer('runtime')->nullable();

            $table->unsignedBigInteger('tvdb_id')->nullable();

            $table->integer('sonarr_episode_file_id')->nullable();
            $table->unsignedBigInteger('sonarr_series_id')->nullable();
            $table->dateTime('aired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
