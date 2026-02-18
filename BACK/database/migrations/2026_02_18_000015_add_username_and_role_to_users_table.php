<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable()->after('username');
            }
        });
        // ensure compatibility: add any other CI fields that may be required later
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) $table->string('first_name', 50)->nullable();
            if (!Schema::hasColumn('users', 'last_name')) $table->string('last_name', 50)->nullable();
            if (!Schema::hasColumn('users', 'birth_date')) $table->date('birth_date')->nullable();
            if (!Schema::hasColumn('users', 'hire_date')) $table->date('hire_date')->nullable();
            if (!Schema::hasColumn('users', 'photo')) $table->string('photo')->nullable();
            if (!Schema::hasColumn('users', 'phone')) $table->string('phone', 20)->nullable();
            if (!Schema::hasColumn('users', 'user_type_id')) $table->unsignedInteger('user_type_id')->nullable();
            if (!Schema::hasColumn('users', 'team_id')) $table->unsignedInteger('team_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role']);
        });
    }
};
