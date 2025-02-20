<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completed_shows', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Show::class);
            $table->foreignIdFor(\App\Models\User::class);

            $table->dateTime('completed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completed_shows');
    }
};
