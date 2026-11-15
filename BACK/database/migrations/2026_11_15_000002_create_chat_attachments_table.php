<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files and images sent in the chat. The message (inside the conversation
 * JSON of `notifications`) keeps a small reference; the file lives on the
 * private `local` disk and only the two participants can download it.
 * Sizes count towards the company's plan storage, like documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            // notifications.id is an old int column: plain index, no FK
            $table->unsignedBigInteger('notification_id')->nullable()->index();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'uploader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
