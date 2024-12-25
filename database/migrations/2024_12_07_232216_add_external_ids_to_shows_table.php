<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->unsignedBigInteger('trakt_id')->nullable()->after('sonarr_id');
            $table->unsignedBigInteger('tvdb_id')->nullable()->after('sonarr_id');
            $table->unsignedBigInteger('tmdb_id')->nullable()->after('sonarr_id');
            $table->string('imdb_id')->nullable()->after('sonarr_id');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn('trakt_id');
            $table->dropColumn('tvdb_id');
            $table->dropColumn('tmdb_id');
            $table->dropColumn('imdb_id');
        });
    }
};
