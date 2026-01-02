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
        Schema::create('episode_search_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Episode::class)->constrained()->cascadeOnDelete();
            $table->integer('sonarr_episode_id')->nullable();
            $table->integer('sonarr_command_id');
            $table->string('status')->default('pending'); // pending, queued, started, completed, failed
            $table->string('result')->nullable(); // added_to_queue, nothing_found, already_imported, error
            $table->text('result_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('sonarr_command_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episode_search_requests');
    }
};
