<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant schema: a single database where every company-owned row
 * carries company_id.
 *
 * - users.company_id is NULL only for platform super admins.
 * - company_id is nullable for now; the tenant phase makes it NOT NULL once
 *   every insert fills it automatically.
 * - Child tables (user_schedules, post_reads, role_permission) inherit the
 *   company through their parent and get no column.
 */
return new class extends Migration
{
    /** Tables whose rows belong to one company. */
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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->string('plan', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('company_id');
        });

        foreach (self::TENANT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            });
        }

        // Role names are unique inside a company, not globally.
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });

        // Stable code for the event types the code relies on (replaces the
        // hard-coded event_type_id = 2 for vacations, which breaks as soon as
        // each company has its own event types).
        Schema::table('event_types', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->unique(['company_id', 'code']);
        });

        // Per-company holidays (calendar + CSV import come in a later phase).
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('date');
            $table->string('name', 150);
            $table->string('type', 20)->default('company'); // national | regional | company
            $table->boolean('recurring')->default(false);   // repeats every year (e.g. 25/12)
            $table->timestamps();
            $table->unique(['company_id', 'date']);
        });

        $this->backfillExistingData();
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');

        // Foreign keys first: MySQL uses the composite unique indexes below
        // (which start with company_id) to back the company_id foreign key.
        foreach (self::TENANT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
        }

        Schema::table('event_types', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code']);
            $table->dropColumn('code');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
        });

        foreach (self::TENANT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('is_super_admin');
        });

        Schema::dropIfExists('companies');
    }

    /**
     * An existing single-company install becomes "Empresa Demo".
     * No-op on a fresh database (seeders create the companies).
     */
    private function backfillExistingData(): void
    {
        DB::table('event_types')->whereRaw('LOWER(name) LIKE ?', ['%vacacion%'])->update(['code' => 'vacation']);
        DB::table('event_types')->whereRaw('LOWER(name) LIKE ?', ['%fichaj%'])->update(['code' => 'attendance_request']);

        if (! DB::table('users')->exists()) {
            return;
        }

        $now = now();
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Empresa Demo',
            'slug' => 'demo',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->whereNull('company_id')->update(['company_id' => $companyId]);
        foreach (self::TENANT_TABLES as $name) {
            DB::table($name)->whereNull('company_id')->update(['company_id' => $companyId]);
        }
    }
};
