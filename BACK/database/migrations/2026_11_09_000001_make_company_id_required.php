<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Now that every insert gets company_id automatically (BelongsToCompany),
 * company-owned rows can no longer exist without a company.
 *
 * users.company_id stays nullable: NULL identifies the platform super admin.
 */
return new class extends Migration
{
    private const TENANT_TABLES = [
        'teams',
        'roles',
        'schedules',
        'attendances',
        'events',
        'event_types',
        'documents',
        'posts',
        'notifications',
        'user_dashboard_widgets',
    ];

    public function up(): void
    {
        $this->assignOrphanRows();

        foreach (self::TENANT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TENANT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }
    }

    /**
     * Rows created between the schema phase and this one may lack a company:
     * take it from the owning user when possible, otherwise from the only
     * company. Anything still orphan cannot be attributed and is removed.
     */
    private function assignOrphanRows(): void
    {
        $userColumn = [
            'attendances' => 'user_id',
            'events' => 'user_id',
            'documents' => 'user_id',
            'posts' => 'user_id',
            'notifications' => 'sender_id',
            'user_dashboard_widgets' => 'user_id',
        ];

        foreach ($userColumn as $name => $column) {
            DB::table($name)
                ->whereNull('company_id')
                ->update([
                    'company_id' => DB::raw("(select users.company_id from users where users.id = {$name}.{$column})"),
                ]);
        }

        $companies = DB::table('companies')->pluck('id');
        foreach (self::TENANT_TABLES as $name) {
            if ($companies->count() === 1) {
                DB::table($name)->whereNull('company_id')->update(['company_id' => $companies->first()]);
            }
            DB::table($name)->whereNull('company_id')->delete();
        }
    }
};
