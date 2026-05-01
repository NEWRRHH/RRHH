<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'approval_status')) {
                $table->string('approval_status', 20)->default('approved')->after('color');
            }
            if (!Schema::hasColumn('events', 'approval_comment')) {
                $table->text('approval_comment')->nullable()->after('approval_status');
            }
            if (!Schema::hasColumn('events', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('approval_comment');
                $table->foreign('reviewed_by_user_id')->references('id')->on('users')->onUpdate('cascade')->nullOnDelete();
            }
            if (!Schema::hasColumn('events', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'reviewed_by_user_id')) {
                $table->dropForeign(['reviewed_by_user_id']);
                $table->dropColumn('reviewed_by_user_id');
            }
            if (Schema::hasColumn('events', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('events', 'approval_comment')) {
                $table->dropColumn('approval_comment');
            }
            if (Schema::hasColumn('events', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};

