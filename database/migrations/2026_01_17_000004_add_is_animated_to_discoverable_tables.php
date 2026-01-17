<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discoverable_shows', function (Blueprint $table) {
            $table->boolean('is_animated')->default(false)->after('origin_countries');
            $table->index('is_animated');
        });

        Schema::table('discoverable_movies', function (Blueprint $table) {
            $table->boolean('is_animated')->default(false)->after('origin_countries');
            $table->index('is_animated');
        });
    }

    public function down(): void
    {
        Schema::table('discoverable_shows', function (Blueprint $table) {
            $table->dropIndex(['is_animated']);
            $table->dropColumn('is_animated');
        });

        Schema::table('discoverable_movies', function (Blueprint $table) {
            $table->dropIndex(['is_animated']);
            $table->dropColumn('is_animated');
        });
    }
};

