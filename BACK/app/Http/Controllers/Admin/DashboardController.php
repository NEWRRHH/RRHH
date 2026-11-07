<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ErrorLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide numbers for the super admin home page.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $from30 = $today->copy()->subDays(29);

        $employees = User::query()->whereNotNull('company_id');

        return response()->json([
            'totals' => [
                'companies' => Company::count(),
                'companies_active' => Company::where('status', Company::STATUS_ACTIVE)->count(),
                'companies_suspended' => Company::where('status', Company::STATUS_SUSPENDED)->count(),
                'companies_new_month' => Company::where('created_at', '>=', $today->copy()->startOfMonth())->count(),
                'employees' => (clone $employees)->count(),
                'employees_new_month' => (clone $employees)->where('created_at', '>=', $today->copy()->startOfMonth())->count(),
                'logins_today' => AuditLog::where('action', 'auth.login')->where('created_at', '>=', $today)->count(),
                'attendances_today' => Attendance::query()->whereDate('date', $today)->count(),
                'working_now' => Attendance::query()->whereDate('date', $today)->where('status', 'en_trabajo')->whereNull('end_date')->count(),
                'errors_open' => ErrorLog::whereNull('resolved_at')->count(),
                'errors_today' => ErrorLog::where('last_seen_at', '>=', $today)->count(),
            ],
            'charts' => [
                'companies_per_month' => $this->companiesPerMonth($today),
                'employees_by_company' => $this->employeesByCompany(),
                'activity_per_day' => $this->activityPerDay($from30, $today),
                'errors_per_day' => $this->errorsPerDay($from30, $today),
            ],
            'latest_errors' => ErrorLog::with('company:id,name')
                ->whereNull('resolved_at')
                ->orderByDesc('last_seen_at')
                ->limit(8)
                ->get(['id', 'company_id', 'exception', 'message', 'occurrences', 'last_seen_at']),
            'latest_companies' => Company::withCount('users')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name', 'slug', 'status', 'created_at']),
        ]);
    }

    /** New companies per month, last 12 months (zero-filled). */
    private function companiesPerMonth(Carbon $today): array
    {
        $start = $today->copy()->startOfMonth()->subMonths(11);
        $counts = Company::withTrashed()
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = [];
        $values = [];
        for ($m = $start->copy(); $m->lte($today); $m->addMonth()) {
            $labels[] = $m->format('Y-m');
            $values[] = (int) ($counts[$m->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Top 10 companies by number of employees. */
    private function employeesByCompany(): array
    {
        $rows = Company::withCount('users')->orderByDesc('users_count')->limit(10)->get(['id', 'name']);

        return [
            'labels' => $rows->pluck('name')->all(),
            'values' => $rows->pluck('users_count')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** Logins and clock-ins per day, last 30 days. */
    private function activityPerDay(Carbon $from, Carbon $to): array
    {
        $logins = AuditLog::where('action', 'auth.login')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $attendances = Attendance::query()
            ->whereDate('date', '>=', $from)
            ->selectRaw('DATE(date) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        return $this->daily($from, $to, ['logins' => $logins, 'attendances' => $attendances]);
    }

    /** Error occurrences per day (by last time seen), last 30 days. */
    private function errorsPerDay(Carbon $from, Carbon $to): array
    {
        $errors = ErrorLog::where('last_seen_at', '>=', $from)
            ->selectRaw('DATE(last_seen_at) as d, SUM(occurrences) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        return $this->daily($from, $to, ['errors' => $errors]);
    }

    /**
     * @param  array<string, Collection>  $series
     */
    private function daily(Carbon $from, Carbon $to, array $series): array
    {
        $out = ['labels' => []];
        foreach (array_keys($series) as $key) {
            $out[$key] = [];
        }
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->toDateString();
            $out['labels'][] = $key;
            foreach ($series as $name => $values) {
                $out[$name][] = (int) ($values[$key] ?? 0);
            }
        }

        return $out;
    }
}
