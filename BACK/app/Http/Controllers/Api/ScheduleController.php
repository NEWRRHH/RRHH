<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\Request;

/**
 * Work-shift templates (jornadas) and schedule assignment.
 */
class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    private function canManageScheduleTemplates(?object $user): bool
    {
        return $user->hasPermission('schedules.manage');
    }

    public function scheduleTemplates(Request $request)
    {
        $user = $request->user();
        if (!$this->canManageScheduleTemplates($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $templates = $this->schedules->loadScheduleTemplatesCollection()
            ->map(fn ($s) => $this->schedules->formatScheduleTemplate($s))
            ->values();

        return response()->json(['schedules' => $templates]);
    }

    public function createScheduleTemplate(Request $request)
    {
        $user = $request->user();
        if (!$this->canManageScheduleTemplates($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'days' => 'required|array|min:1',
            'days.*' => 'in:L,M,X,J,V,S,D',
        ]);

        $days = array_values(array_unique($this->schedules->normalizeScheduleDays($data['days'] ?? null)));

        $exists = $this->schedules->scheduleTemplateExists((string) $data['start_time'], (string) $data['end_time'], $days);

        if ($exists) {
            return response()->json(['message' => 'Ese horario ya existe'], 422);
        }

        $id = Schedule::table()->insertGetId([
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'days' => json_encode($days),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schedule = Schedule::table()->where('id', $id)->first(['id', 'start_time', 'end_time', 'days']);

        return response()->json([
            'status' => 'ok',
            'schedule' => $schedule ? $this->schedules->formatScheduleTemplate($schedule) : null,
        ], 201);
    }

    public function updateScheduleTemplate(Request $request, int $id)
    {
        $user = $request->user();
        if (!$this->canManageScheduleTemplates($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $schedule = Schedule::table()
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first(['id']);

        if (!$schedule) {
            return response()->json(['message' => 'Jornada no encontrada'], 404);
        }

        $data = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'days' => 'required|array|min:1',
            'days.*' => 'in:L,M,X,J,V,S,D',
        ]);

        $days = array_values(array_unique($this->schedules->normalizeScheduleDays($data['days'] ?? null)));

        $exists = $this->schedules->scheduleTemplateExists((string) $data['start_time'], (string) $data['end_time'], $days, $id);

        if ($exists) {
            return response()->json(['message' => 'Ya existe una jornada con esos datos'], 422);
        }

        Schedule::table()
            ->where('id', $id)
            ->update([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'days' => json_encode($days),
                'updated_at' => now(),
            ]);

        $updated = Schedule::table()->where('id', $id)->first(['id', 'start_time', 'end_time', 'days']);

        return response()->json([
            'status' => 'ok',
            'schedule' => $updated ? $this->schedules->formatScheduleTemplate($updated) : null,
        ]);
    }

    public function deleteScheduleTemplate(Request $request, int $id)
    {
        $user = $request->user();
        if (!$this->canManageScheduleTemplates($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $schedule = Schedule::table()
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first(['id']);

        if (!$schedule) {
            return response()->json(['message' => 'Jornada no encontrada'], 404);
        }

        Schedule::table()
            ->where('id', $id)
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Update schedule for authenticated user (admin only).
     */
    public function updateSchedule(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('schedules.manage')) {
            return response()->json(['message'=>'Forbidden'], 403);
        }
        $data = $request->validate([
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'in:L,M,X,J,V,S,D',
            'schedule_template_ids' => 'nullable|array',
            'schedule_template_ids.*' => 'integer|exists:schedules,id',
        ]);
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
                $days = array_values(array_unique($templateDays));
                $firstTemplate = $templates->first();
                $startTime = isset($firstTemplate->start_time) ? substr((string) $firstTemplate->start_time, 0, 5) : $startTime;
                $endTime = isset($firstTemplate->end_time) ? substr((string) $firstTemplate->end_time, 0, 5) : $endTime;
            }
        }

        $this->schedules->syncUserScheduleTemplates((int) $user->id, $templates);

        if (!count($templates)) {
            return response()->json(['message' => 'Debes seleccionar al menos una jornada existente'], 422);
        }

        return response()->json(['status'=>'ok']);
    }
}
