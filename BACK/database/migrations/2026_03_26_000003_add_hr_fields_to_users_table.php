<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'dni')) {
                $table->string('dni', 30)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'social_security_number')) {
                $table->string('social_security_number', 50)->nullable()->after('dni');
            }
            if (!Schema::hasColumn('users', 'contract_type')) {
                $table->string('contract_type', 50)->nullable()->after('social_security_number');
            }
            if (!Schema::hasColumn('users', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable()->after('contract_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'contract_start_date')) $table->dropColumn('contract_start_date');
            if (Schema::hasColumn('users', 'contract_type')) $table->dropColumn('contract_type');
            if (Schema::hasColumn('users', 'social_security_number')) $table->dropColumn('social_security_number');
            if (Schema::hasColumn('users', 'dni')) $table->dropColumn('dni');
        });
    }
};

