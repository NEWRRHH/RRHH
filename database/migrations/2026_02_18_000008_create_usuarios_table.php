<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration was originally creating a separate Spanish-named `Usuarios` table.
        // Final schema uses the main `users` table (see base users migration).
        // Keep this file as a no-op to avoid duplicate creation when running migrations from scratch.
        // (Existing 'Usuarios' table — if present in old DB — should be migrated separately.)
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
