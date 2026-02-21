<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // add a nullable title column if it doesn't already exist (fresh installs
            // may have been modified earlier and already include it)
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title', 255)->nullable()->after('notification_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
