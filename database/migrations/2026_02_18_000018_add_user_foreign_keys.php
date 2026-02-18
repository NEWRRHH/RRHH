<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'user_type_id')) {
                    $table->foreign('user_type_id')->references('id')->on('user_types')->onUpdate('cascade')->onDelete('cascade');
                }
                if (Schema::hasColumn('users', 'team_id')) {
                    $table->foreign('team_id')->references('id')->on('teams')->onUpdate('cascade')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                // drop foreign keys if exist
                try { $table->dropForeign(['user_type_id']); } catch (\Exception $e) { }
                try { $table->dropForeign(['team_id']); } catch (\Exception $e) { }
            });
        }
    }
};
