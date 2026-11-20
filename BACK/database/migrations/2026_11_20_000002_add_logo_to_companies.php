<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company logo (uploaded on signup or from Configuración): shown in the
 * sidebar and used as the browser tab icon (favicon).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'logo')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('logo', 500)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'logo')) {
            Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('logo'));
        }
    }
};
