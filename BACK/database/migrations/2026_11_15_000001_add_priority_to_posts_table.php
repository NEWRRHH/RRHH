<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcement priority: normal | importante | urgente (colour in the UI).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'priority')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->string('priority', 20)->default('normal')->after('audience_scope');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'priority')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    }
};
