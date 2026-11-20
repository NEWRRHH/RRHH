<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * documents.read_at: when the employee saw the document. Payslips registered
 * by PayrollSync start unread -> badge in the Documentos menu and card.
 * Existing documents count as already seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documents', 'read_at')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->timestamp('read_at')->nullable()->after('category');
            });
            DB::table('documents')->update(['read_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'read_at')) {
            Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('read_at'));
        }
    }
};
