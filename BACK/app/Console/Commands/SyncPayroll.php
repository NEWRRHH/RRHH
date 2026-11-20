<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\PayrollSync;
use App\Support\Tenancy\Tenant;
use Illuminate\Console\Command;

/**
 * Registers the payslips dropped in companies/{id}/nominas/{employee}/ as
 * "payroll" documents (Documentos > Nóminas) and lists the folders that
 * don't match any employee.
 *
 *   php artisan nominas:sync               # every company
 *   php artisan nominas:sync --company=3
 */
class SyncPayroll extends Command
{
    protected $signature = 'nominas:sync {--company= : only this company id}';

    protected $description = 'Register payslips (nóminas) dropped in each employee folder';

    public function handle(PayrollSync $sync): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($companies as $company) {
            $result = Tenant::run($company->id, fn () => $sync->syncCompany($company->id));
            $this->info("{$company->name}: {$result['created']} nómina(s) nuevas, {$result['removed']} quitada(s) (el archivo ya no está en la carpeta)");
            foreach ($result['unmatched'] as $dir) {
                $this->warn("  sin empleado: {$dir}");
            }
            foreach ($result['ambiguous'] as $dir) {
                $this->warn("  coincide con varios empleados (usa DNI o email): {$dir}");
            }
        }

        return self::SUCCESS;
    }
}
