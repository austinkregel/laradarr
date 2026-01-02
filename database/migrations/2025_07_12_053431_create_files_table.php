<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->text('path');
            $table->string('name')->index();

            $table->string('extension')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('disk')->index()->default('local');
            $table->string('visibility')->default('private');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('md5_checksum')->nullable();

            $table->integer('permissions')->default(0);
            $table->string('owner')->nullable();
            $table->string('group')->nullable();
            $table->timestamp('created_on')->nullable();
            $table->timestamp('last_modified')->nullable();

            // Season, Episode, language, executable, subtitles, lyrics, artist, song title, album, etc.
            $table->json('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
