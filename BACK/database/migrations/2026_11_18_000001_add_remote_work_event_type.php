<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Teletrabajo" request type for every existing company (new companies get it
 * from CompanyProvisioner). Approved remote-work days show the house icon on
 * the avatars and the "Teletrabajo hoy" list in Reportes > Who's in.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $exists = DB::table('event_types')
                ->where('company_id', $companyId)
                ->where(fn ($q) => $q->where('code', 'remote_work')->orWhere('name', 'Teletrabajo'))
                ->exists();
            if ($exists) {
                DB::table('event_types')->where('company_id', $companyId)->where('name', 'Teletrabajo')->whereNull('code')->update(['code' => 'remote_work']);
                continue;
            }
            DB::table('event_types')->insert([
                'company_id' => $companyId,
                'name' => 'Teletrabajo',
                'code' => 'remote_work',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // keep the type: requests may already use it
    }
};
