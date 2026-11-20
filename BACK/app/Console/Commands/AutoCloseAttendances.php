<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AttendanceAutoClose;
use Illuminate\Console\Command;

/**
 * Closes the clock-ins of past days that the employee forgot to close, at
 * the end of their schedule, with a note (see AttendanceAutoClose). Runs
 * every hour from the scheduler, so right after midnight yesterday's open
 * clock-ins are closed.
 *
 *   php artisan attendance:auto-close [--company=3]
 */
class AutoCloseAttendances extends Command
{
    protected $signature = 'attendance:auto-close {--company= : only this company id}';

    protected $description = 'Close the clock-ins of past days left open, at the end of the employee schedule';

    public function handle(AttendanceAutoClose $autoClose): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->get(['id', 'name']);

        foreach ($companies as $company) {
            $closed = $autoClose->closeCompany($company->id);
            if ($closed) {
                $this->info("{$company->name}: {$closed} fichaje(s) cerrados automáticamente");
            }
        }

        return self::SUCCESS;
    }
}
