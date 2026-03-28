<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'audience_scope')) {
                $table->string('audience_scope', 20)->default('all')->after('user_id');
            }
            if (!Schema::hasColumn('posts', 'team_id')) {
                // teams.id is INT UNSIGNED (increments), so team_id must match.
                $table->unsignedInteger('team_id')->nullable()->after('audience_scope');
            }
        });

        // If a previous failed run left team_id as BIGINT, normalize it to INT UNSIGNED.
        DB::statement('ALTER TABLE posts MODIFY team_id INT UNSIGNED NULL');

        $hasForeign = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'posts')
            ->where('COLUMN_NAME', 'team_id')
            ->where('REFERENCED_TABLE_NAME', 'teams')
            ->exists();

        if (!$hasForeign) {
            Schema::table('posts', function (Blueprint $table) {
                $table->foreign('team_id')
                    ->references('id')
                    ->on('teams')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        $hasForeign = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'posts')
            ->where('COLUMN_NAME', 'team_id')
            ->where('REFERENCED_TABLE_NAME', 'teams')
            ->exists();

        if ($hasForeign) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropForeign(['team_id']);
            });
        }

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'team_id')) {
                $table->dropColumn('team_id');
            }
            if (Schema::hasColumn('posts', 'audience_scope')) {
                $table->dropColumn('audience_scope');
            }
        });
    }
};
