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
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn('seasons');
            $table->dropColumn('episodes');
            $table->integer('season_count')->nullable();
            $table->integer('episode_count')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->integer('seasons')->nullable();
            $table->integer('episodes')->nullable();
        });
    }
};
