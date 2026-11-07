<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\ScheduleService;
use App\Support\WorkTime;
use Carbon\Carbon;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Employee (user) management inside the company.
 */
class EmployeeController extends Controller
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    /**
     * List all employees (admin only) with team name.
     */
    public function employees(Request $request)
    {
        $user = $request->user();
        if ($user->cannot('employees.view_details')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employees = User::table()
            ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.photo',
                'users.profile_photo_path',
                'users.team_id',
                'teams.name as team_name',
                'users.role_id',
                'roles.name as role_name'
            )
            ->whereNull('users.deleted_at')
            ->orderBy('users.id', 'asc')
            ->get()
            ->map(function ($employee) {
                $row = (array) $employee;
                $row['assigned_schedule_templates'] = $this->schedules->loadAssignedScheduleTemplatesForUser((int) $employee->id);
                return $row;
            })
            ->values();

        $teams = Team::table()->select('id', 'name')->orderBy('name')->get();
        $scheduleTemplates = $this->schedules->loadScheduleTemplatesCollection()
            ->map(fn ($s) => $this->schedules->formatScheduleTemplate($s))
            ->values();

        return response()->json([
            'employees' => $employees,
            'teams' => $teams,
            'roles' => $this->assignableRoles($user),
            'schedule_templates' => $scheduleTemplates,
        ]);
    }

    /**
     * Create one employee user.
     */
    public function createEmployee(Request $request)
    {
        $user = $request->user();
        if ($user->cannot('employees.create')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => ['required','confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'team_id' => ['nullable', 'integer', TenantRule::exists('teams', 'id', true)],
            'role_id' => ['nullable', 'integer', TenantRule::exists('roles')],
            'photo' => 'nullable|image|max:2048',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'hire_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'birth_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'dni' => 'nullable|string|max:30',
            'social_security_number' => 'nullable|string|max:50',
            'contract_type' => 'nullable|string|max:50',
            'contract_start_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'vacation_days_total' => 'nullable|integer|min:0|max:365',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'in:L,M,X,J,V,S,D',
            'schedule_template_ids' => 'nullable|array',
            'schedule_template_ids.*' => ['integer', TenantRule::exists('schedules', 'id', true)],
        ]);

        // Privilege escalation guard: only an admin may grant the admin role
        // (see UserPolicy::assignRole). Otherwise a user holding only
        // "employees.create" could mint admins.
        $role = isset($data['role_id']) ? Role::find((int) $data['role_id']) : null;
        if ($user->cannot('assignRole', [User::class, $role])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = new User();
        $employee->name = $data['name'];
        $employee->username = $data['username'] ?? null;
        $employee->email = $data['email'];
        $employee->password = Hash::make($data['password']);
        $employee->team_id = $data['team_id'] ?? null;
        $employee->role_id = $role?->id;
        $employee->first_name = $data['first_name'] ?? null;
        $employee->last_name = $data['last_name'] ?? null;
        $employee->phone = $data['phone'] ?? null;
        $employee->hire_date = $data['hire_date'] ?? null;
        $employee->birth_date = $data['birth_date'] ?? null;
        $employee->dni = $data['dni'] ?? null;
        $employee->social_security_number = $data['social_security_number'] ?? null;
        $employee->contract_type = $data['contract_type'] ?? null;
        $employee->contract_start_date = $data['contract_start_date'] ?? null;
        $employee->vacation_days_total = isset($data['vacation_days_total']) ? (int) $data['vacation_days_total'] : 0;

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars/' . $user->company_id, 'public');
            $employee->photo = '/storage/' . $path;
        }

        $employee->save();

        if ($user->can('employees.create')) {
            $days = array_values(array_unique($this->schedules->normalizeScheduleDays($data['days'] ?? null)));
            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            $templates = collect();

            $templateIds = $data['schedule_template_ids'] ?? [];
            if (count($templateIds)) {
                $templates = $this->schedules->getScheduleTemplatesByIds($templateIds);

                if ($this->schedules->hasOverlappingDaysInTemplates($templates)) {
                    return response()->json(['message' => 'No puedes seleccionar jornadas con dias repetidos'], 422);
                }

                if ($templates->count()) {
                    $templateDays = [];
                    foreach ($templates as $tpl) {
                        $templateDays = array_merge($templateDays, $this->schedules->normalizeScheduleDays($tpl->days ?? null));
                    }
                    if (!count($days)) {
                        $days = array_values(array_unique($templateDays));
                    }
                    if (!$startTime || !$endTime) {
                        $firstTemplate = $templates->first();
                        $startTime = $startTime ?: (isset($firstTemplate->start_time) ? substr((string) $firstTemplate->start_time, 0, 5) : null);
                        $endTime = $endTime ?: (isset($firstTemplate->end_time) ? substr((string) $firstTemplate->end_time, 0, 5) : null);
                    }
                }
            }

            $this->schedules->syncUserScheduleTemplates((int) $employee->id, $templates);
        }

        return response()->json([
            'status' => 'ok',
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
            ],
        ], 201);
    }

    /**
     * Get one employee with schedule and teams (admin only).
     */
    public function getEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('employees.view_details')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::with('role:id,name,is_admin')->find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $employee->id);
        if ($schedule) {
            $schedule->days = $this->schedules->normalizeScheduleDays($schedule->days ?? null);
        }

        $teams = Team::table()->select('id', 'name')->orderBy('name')->get();
        $scheduleTemplates = $this->schedules->loadScheduleTemplatesCollection()
            ->map(fn ($s) => $this->schedules->formatScheduleTemplate($s))
            ->values();

        return response()->json([
            'employee' => $employee,
            'schedule' => $schedule,
            'teams' => $teams,
            'roles' => $this->assignableRoles($user),
            'can_change_role' => $user->can('assignRole', [User::class, $employee->role]) && $user->id !== $employee->id,
            'schedule_templates' => $scheduleTemplates,
            'schedule_template_ids' => $this->schedules->getAssignedScheduleTemplateIds((int) $employee->id),
            'assigned_schedule_templates' => $this->schedules->loadAssignedScheduleTemplatesForUser((int) $employee->id),
        ]);
    }

    /**
     * Monthly attendance data for one employee (admin only).
     */
    public function employeeAttendanceMonth(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('employees.view_details')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::whereNull('deleted_at')->find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $monthInput = (string) ($request->query('month') ?: Carbon::today()->format('Y-m'));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Exception $e) {
            $monthDate = Carbon::today()->startOfMonth();
        }

        $includeNonWorking = (bool) ((int) $request->query('include_non_working', 0));
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();

        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $employee->id);
        $scheduleDays = $this->schedules->normalizeScheduleDays($schedule->days ?? null);
        $dailyTargetMinutes = $this->schedules->scheduleDailyMinutes($schedule);

        $rawRows = Attendance::table()
            ->where('user_id', $employee->id)
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get([
                'id',
                'date',
                'start_time',
                'pause_time',
                'resume_time',
                'end_time',
                'hours_worked',
                'status',
            ]);

        $rowsByDate = [];
        foreach ($rawRows as $r) {
            if (!isset($rowsByDate[$r->date])) {
                $rowsByDate[$r->date] = $r;
            }
        }

        $rows = [];
        $targetMinutes = 0;
        $workedMinutes = 0;
        $weekMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

        $workedDaysCount = 0;
        $workingDaysInMonth = 0;

        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $att = $rowsByDate[$key] ?? null;
            $dayLetter = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
            $isWorkingDay = in_array($dayLetter, $scheduleDays, true);

            if ($isWorkingDay) {
                $targetMinutes += $dailyTargetMinutes;
                $workingDaysInMonth++;
            }

            $rowWorked = 0;
            if ($att && !empty($att->hours_worked)) {
                $rowWorked = WorkTime::timeStringToMinutes((string) $att->hours_worked);
            }
            if ($rowWorked > 0) {
                $workedDaysCount++;
            }
            $workedMinutes += $rowWorked;

            if ($isWorkingDay || $includeNonWorking) {
                $rows[] = [
                    'date' => $key,
                    'weekday' => $dayLetter,
                    'start_time' => $att->start_time ?? null,
                    'pause_time' => $att->pause_time ?? null,
                    'resume_time' => $att->resume_time ?? null,
                    'end_time' => $att->end_time ?? null,
                    'hours_worked' => $att->hours_worked ?? null,
                    'status' => $att->status ?? null,
                    'is_working_day' => $isWorkingDay,
                ];
            }

            $cursor->addDay();
        }

        $requestRows = Event::table()
            ->where('user_id', $employee->id)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhereBetween('end_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_at', '<=', $monthStart->toDateTimeString())
                            ->where(function ($q3) use ($monthEnd) {
                                $q3->where('end_at', '>=', $monthEnd->toDateTimeString())
                                    ->orWhereNull('end_at');
                            });
                    });
            })
            ->get(['approval_status']);

        $requestsTotal = 0;
        $requestsPending = 0;
        $requestsApproved = 0;
        $requestsRejected = 0;
        foreach ($requestRows as $rq) {
            $requestsTotal++;
            $st = strtolower(trim((string) ($rq->approval_status ?? 'approved')));
            if ($st === 'pending') $requestsPending++;
            elseif ($st === 'rejected') $requestsRejected++;
            else $requestsApproved++;
        }

        return response()->json([
            'month' => $monthDate->format('Y-m'),
            'employee_id' => $employee->id,
            'summary' => [
                'worked_minutes' => $workedMinutes,
                'worked_hhmm' => WorkTime::formatMinutesAsHHMM($workedMinutes),
                'target_minutes' => $targetMinutes,
                'target_hhmm' => WorkTime::formatMinutesAsHHMM($targetMinutes),
                'worked_days' => $workedDaysCount,
                'working_days' => $workingDaysInMonth,
                'missing_days' => max(0, $workingDaysInMonth - $workedDaysCount),
                'compliance_percent' => $targetMinutes > 0 ? (int) round(($workedMinutes / $targetMinutes) * 100) : 0,
                'requests_total' => $requestsTotal,
                'requests_pending' => $requestsPending,
                'requests_approved' => $requestsApproved,
                'requests_rejected' => $requestsRejected,
            ],
            'schedule' => [
                'start_time' => $schedule->start_time ?? null,
                'end_time' => $schedule->end_time ?? null,
                'days' => $scheduleDays,
                'daily_target_minutes' => $dailyTargetMinutes,
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * Documents for one employee (admin only).
     */
    public function employeeDocuments(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('employees.view_details')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::whereNull('deleted_at')->find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $category = (string) $request->query('category', '');
        $q = Document::table()
            ->where('user_id', $employee->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        if (in_array($category, ['medical', 'receipt', 'payroll'], true)) {
            $q->where('category', $category);
        }

        $docs = $q->get([
            'id',
            'description',
            'filename',
            'original_name',
            'mime_type',
            'size',
            'category',
            'created_at',
        ]);

        return response()->json(['documents' => $docs]);
    }

    /**
     * Download one employee document (admin only).
     */
    public function employeeDocumentDownload(Request $request, int $employeeId, int $docId)
    {
        $user = $request->user();
        if ($user->cannot('employees.view_details')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::whereNull('deleted_at')->find($employeeId);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $doc = Document::table()
            ->where('id', $docId)
            ->where('user_id', $employee->id)
            ->whereNull('deleted_at')
            ->first(['id', 'filename', 'original_name']);

        if (!$doc || empty($doc->filename)) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }
        if (!Storage::disk('local')->exists($doc->filename)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        $downloadName = $doc->original_name ?: basename((string) $doc->filename);
        return Storage::disk('local')->download($doc->filename, $downloadName);
    }

    /**
     * Update one employee (admin only).
     */
    public function updateEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('employees.edit')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        // Account-takeover guard (UserPolicy::update): only an admin may edit
        // an admin account, otherwise a user holding just "employees.edit"
        // could change an admin's email/password and log in as them.
        if ($user->cannot('update', $employee)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'team_id' => ['nullable', 'integer', TenantRule::exists('teams', 'id', true)],
            'role_id' => ['nullable', 'integer', TenantRule::exists('roles')],
            'photo' => 'nullable|image|max:2048',
            'phone' => 'nullable|string|max:20',
            'hire_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'birth_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'dni' => 'nullable|string|max:30',
            'social_security_number' => 'nullable|string|max:50',
            'contract_type' => 'nullable|string|max:50',
            'contract_start_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'vacation_days_total' => 'nullable|integer|min:0|max:365',
            'password' => ['nullable','confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'in:L,M,X,J,V,S,D',
            'schedule_template_ids' => 'nullable|array',
            'schedule_template_ids.*' => ['integer', TenantRule::exists('schedules', 'id', true)],
        ]);

        // Role change: only when the field is sent, never on yourself (an
        // admin could lock themselves out), and the new role must be one the
        // caller may assign (only admins may grant the admin role).
        if ($request->has('role_id')) {
            $newRole = isset($data['role_id']) ? Role::find((int) $data['role_id']) : null;
            if ((int) ($newRole?->id) !== (int) $employee->role_id) {
                if ($user->id === $employee->id) {
                    return response()->json(['message' => 'No puedes cambiar tu propio rol'], 422);
                }
                if ($user->cannot('assignRole', [User::class, $newRole])) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                $employee->role_id = $newRole?->id;
            }
        }

        $employee->name = $data['name'];
        $employee->email = $data['email'];
        $employee->team_id = $data['team_id'] ?? null;
        $employee->phone = $data['phone'] ?? null;
        $employee->hire_date = $data['hire_date'] ?? null;
        $employee->birth_date = $data['birth_date'] ?? null;
        $employee->dni = $data['dni'] ?? null;
        $employee->social_security_number = $data['social_security_number'] ?? null;
        $employee->contract_type = $data['contract_type'] ?? null;
        $employee->contract_start_date = $data['contract_start_date'] ?? null;
        $employee->vacation_days_total = isset($data['vacation_days_total']) ? (int) $data['vacation_days_total'] : (int) ($employee->vacation_days_total ?? 0);
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars/' . $user->company_id, 'public');
            $employee->photo = '/storage/' . $path;
        }
        if (! empty($data['password'])) {
            $employee->password = Hash::make($data['password']);
        }
        $employee->save();

        if ($user->can('employees.edit')) {
            $days = array_values(array_unique($this->schedules->normalizeScheduleDays($data['days'] ?? null)));
            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            $templates = collect();

            $templateIds = $data['schedule_template_ids'] ?? [];
            if (count($templateIds)) {
                $templates = $this->schedules->getScheduleTemplatesByIds($templateIds);
                if ($this->schedules->hasOverlappingDaysInTemplates($templates)) {
                    return response()->json(['message' => 'No puedes seleccionar jornadas con dias repetidos'], 422);
                }

                if ($templates->count()) {
                    $templateDays = [];
                    foreach ($templates as $tpl) {
                        $templateDays = array_merge($templateDays, $this->schedules->normalizeScheduleDays($tpl->days ?? null));
                    }
                    if (!count($days)) {
                        $days = array_values(array_unique($templateDays));
                    }
                    if (!$startTime || !$endTime) {
                        $firstTemplate = $templates->first();
                        $startTime = $startTime ?: (isset($firstTemplate->start_time) ? substr((string) $firstTemplate->start_time, 0, 5) : null);
                        $endTime = $endTime ?: (isset($firstTemplate->end_time) ? substr((string) $firstTemplate->end_time, 0, 5) : null);
                    }
                }
            }

            $this->schedules->syncUserScheduleTemplates((int) $employee->id, $templates);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Delete one employee (admin only).
     */
    public function deleteEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('employees.delete')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->id === $id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 422);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        // Only an admin may delete an admin account (UserPolicy::delete).
        if ($user->cannot('delete', $employee)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee->delete();
        return response()->json(['status' => 'ok']);
    }

    /**
     * Roles the caller may assign: admins see every role, everybody else
     * every role except the admin one.
     */
    private function assignableRoles(User $user)
    {
        return Role::table()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('is_admin', false))
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'is_admin']);
    }
}
