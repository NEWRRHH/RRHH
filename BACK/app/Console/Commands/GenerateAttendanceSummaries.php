<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AttendanceSummaryPublisher;
use App\Support\CompanySettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Monthly attendance summary (PDF) of every employee, fully automatic: kept
 * in Documentos > Asistencia and emailed when the company has it enabled.
 *
 * The scheduler runs it every hour for the month that just ended and only
 * creates the summaries that are still missing, so it catches up by itself
 * (server down on the 1st, employee added later...) and never emails the
 * same summary twice.
 *
 *   php artisan attendance:summaries                         # last month, missing ones
 *   php artisan attendance:summaries --month=2026-10 --force # regenerate a month (no email)
 */
class GenerateAttendanceSummaries extends Command
{
    protected $signature = 'attendance:summaries
        {--month= : YYYY-MM (default: previous month)}
        {--company= : only this company id}
        {--force : regenerate the existing ones too (they are not emailed again)}
        {--no-mail : do not email the employees}';

    protected $description = 'Generate the monthly attendance summary PDF of each employee (Documentos > Asistencia + email)';

    public function handle(AttendanceSummaryPublisher $publisher): int
    {
        try {
            $month = $this->option('month')
                ? Carbon::createFromFormat('Y-m', (string) $this->option('month'))->startOfMonth()
                : Carbon::today()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable) {
            $this->error('--month debe tener el formato YYYY-MM');

            return self::FAILURE;
        }
        if ($month->gte(Carbon::today()->startOfMonth())) {
            $this->error('Solo se generan meses ya terminados.');

            return self::FAILURE;
        }

        $companies = Company::query()
            ->where('status', Company::STATUS_ACTIVE)
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($companies as $company) {
            $email = CompanySettings::get($company->id, 'attendance_summary_email') && ! $this->option('no-mail');

            $done = 0;
            $mailed = 0;
            foreach ($publisher->recipients($company->id, $month) as $user) {
                $exists = $publisher->exists($user, $month);
                if ($exists && ! $this->option('force')) {
                    continue;
                }
                try {
                    // a regenerated summary is not emailed again
                    $result = $publisher->publish($user, $month, $email && ! $exists);
                    $done++;
                    $mailed += $result['emailed'] ? 1 : 0;
                } catch (\Throwable $e) {
                    report($e);
                    $this->warn("  {$user->email}: {$e->getMessage()}");
                }
            }
            if ($done) {
                $this->info("{$company->name}: {$done} resumen(es) de {$month->format('Y-m')}, {$mailed} enviado(s) por email");
            }
        }

        return self::SUCCESS;
    }
}
