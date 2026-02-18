<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 100);
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('event_type_id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('description')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('color', 20)->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('event_type_id')->references('id')->on('event_types')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
