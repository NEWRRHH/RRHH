<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->increments('id');
            $table->text('description');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->softDeletes();
            $table->string('filename', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('size')->nullable();
            $table->boolean('visibility')->default(true);
            $table->unsignedInteger('document_type_id')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('document_type_id')->references('id')->on('document_types')->onUpdate('cascade')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
