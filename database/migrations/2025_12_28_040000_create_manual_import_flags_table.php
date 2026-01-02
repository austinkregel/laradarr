<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_import_flags', function (Blueprint $table) {
            $table->id();
            $table->string('torrent_hash')->unique();
            $table->string('torrent_name');
            $table->text('content_path')->nullable(); // Path to the downloaded content
            $table->json('file_paths')->nullable(); // Array of file paths in the torrent
            $table->text('reason')->nullable(); // Why it needs manual import
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['resolved', 'created_at']);
            $table->index('torrent_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_import_flags');
    }
};



