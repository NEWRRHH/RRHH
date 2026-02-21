<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('notification_type_id');
            // new title field added later in migrations; include for fresh installs
            $table->string('title', 255)->nullable();
            $table->string('content', 500);
            $table->integer('read')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('notification_type_id')->references('id')->on('notification_types')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
