<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_content_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('content_warning_id')->constrained('content_warnings')->cascadeOnDelete();
            $table->string('severity')->default('mild')->index(); // mild|moderate|strong
            $table->timestamps();

            $table->unique(['show_id', 'content_warning_id']);
            $table->index(['content_warning_id', 'show_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_content_warnings');
    }
};





