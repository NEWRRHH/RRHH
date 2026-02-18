<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id');
            $table->string('session_token', 255)->nullable()->default(NULL);
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable()->default(NULL);
            $table->float('hours_worked')->nullable()->default(NULL);
            $table->enum('status', ['en_trabajo', 'fuera_trabajo'])->default('fuera_trabajo');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
