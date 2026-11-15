<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\PayrollSync;
use App\Support\FileStorage;
use App\Support\Tenancy\Tenant;
use Illuminate\Console\Command;

/**
 * Creates the folder tree of every company up front:
 *
 *   companies/{slug}/documents, chat, nominas/{"Juan Pérez - 30111222"}, avatars
 *
 * so RRHH can drop payslips right away. Safe to run again (only adds what is
 * missing). New companies and new employees get their folders automatically.
 *
 *   php artisan files:folders
 *   php artisan files:folders --company=nano-tech
 */
class CreateFolders extends Command
{
    protected $signature = 'files:folders {--company= : only this company (slug or id)}';

    protected $description = 'Create the per-company folders (documents, chat, nominas per employee, avatars)';

    public function handle(PayrollSync $payroll): int
    {
        $only = $this->option('company');
        $companies = Company::query()
            ->when($only, fn ($q) => $q->where(fn ($q2) => $q2->where('slug', $only)->orWhere('id', is_numeric($only) ? (int) $only : 0)))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No hay empresas' . ($only ? " que coincidan con \"{$only}\"" : '') . '.');

            return self::FAILURE;
        }

        $this->info('Disco privado: ' . FileStorage::diskName() . ' · fotos: ' . FileStorage::publicDiskName());
        foreach ($companies as $company) {
            $folders = Tenant::run($company->id, fn () => $payroll->ensureCompanyFolders($company->id));
            $this->info("{$company->name} → companies/" . FileStorage::companyFolder($company->id));
            foreach ($folders as $dir) {
                $this->line("  {$dir}");
            }
        }

        if (FileStorage::diskName() === 'local') {
            $this->line('');
            $this->line('En el servidor están en BACK/storage/app/private/companies/ (fotos: BACK/storage/app/public/companies/).');
        }

        return self::SUCCESS;
    }
}
