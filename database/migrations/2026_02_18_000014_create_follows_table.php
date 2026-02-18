<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->unsignedBigInteger('following_user_id');
            $table->unsignedBigInteger('followed_user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['following_user_id', 'followed_user_id']);

            $table->foreign('following_user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('followed_user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
