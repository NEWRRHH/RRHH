<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NationalHoliday;
use App\Support\Audit;
use App\Support\HolidayCsvParser;
use App\Support\Tenancy\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * National holidays catalog of the company, per country (Configuración >
 * Festivos > Feriados nacionales, "holidays.manage": RRHH / admin).
 * "Importar nacionales" copies them into the company holidays of a year.
 * Tenant scoped: each company keeps its own list.
 */
class NationalHolidayController extends Controller
{
    private function denied(Request $request)
    {
        return $request->user()->cannot('holidays.manage')
            ? response()->json(['message' => 'Forbidden'], 403)
            : null;
    }

    public function index(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $data = $request->validate([
            'country_code' => 'nullable|string|size:2',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);
        $country = strtoupper($data['country_code'] ?? ($request->user()->company?->country_code ?: 'ES'));
        $year = (int) ($data['year'] ?? Carbon::today()->year);

        $holidays = NationalHoliday::where('country_code', $country)
            ->where(fn ($q) => $q->whereYear('date', $year)->orWhere('recurring', true))
            ->orderByRaw('MONTH(date), DAY(date)')
            ->get(['id', 'country_code', 'date', 'name', 'type', 'recurring']);

        return response()->json([
            'country_code' => $country,
            'year' => $year,
            'holidays' => $holidays,
            'countries' => NationalHoliday::query()->distinct()->orderBy('country_code')->pluck('country_code'),
        ]);
    }

    private function rules(string $country, ?int $ignoreId = null): array
    {
        $unique = TenantRule::unique('national_holidays', 'date')->where('country_code', $country);
        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return [
            'country_code' => 'required|string|size:2',
            'date' => ['required', 'date_format:Y-m-d', $unique],
            'name' => 'required|string|max:150',
            'type' => 'nullable|in:national,regional',
            'recurring' => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $country = strtoupper((string) $request->input('country_code'));
        $data = $request->validate($this->rules($country), ['date.unique' => 'Ya hay un feriado cargado en esa fecha.']);
        $data['country_code'] = $country;

        $holiday = NationalHoliday::create(array_filter($data, fn ($v) => $v !== null));
        Audit::log('national_holiday.created', $holiday, $holiday->only(['country_code', 'name']));

        return response()->json(['status' => 'ok', 'holiday' => $holiday], 201);
    }

    public function update(Request $request, int $id)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $holiday = NationalHoliday::findOrFail($id);
        $country = strtoupper((string) $request->input('country_code', $holiday->country_code));
        $data = $request->validate($this->rules($country, $holiday->id), ['date.unique' => 'Ya hay un feriado cargado en esa fecha.']);
        $data['country_code'] = $country;
        $holiday->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['status' => 'ok', 'holiday' => $holiday]);
    }

    public function destroy(Request $request, int $id)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $holiday = NationalHoliday::findOrFail($id);
        $holiday->delete();
        Audit::log('national_holiday.deleted', $holiday, $holiday->only(['country_code', 'name']));

        return response()->json(['status' => 'ok']);
    }

    /**
     * CSV import for one country (preview / commit, like company holidays).
     */
    public function import(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $data = $request->validate([
            'country_code' => 'required|string|size:2',
            'file' => 'required|file|max:1024|mimes:csv,txt',
            'mode' => 'required|in:preview,commit',
            'on_duplicate' => 'nullable|in:skip,overwrite',
        ]);
        $country = strtoupper($data['country_code']);

        $rows = HolidayCsvParser::parse($request->file('file'), ['national', 'regional']);
        $existing = NationalHoliday::where('country_code', $country)->pluck('id', 'date')
            ->mapWithKeys(fn ($id, $date) => [Carbon::parse($date)->toDateString() => $id]);

        foreach ($rows as &$row) {
            $row['status'] = $row['error'] ? 'error' : (isset($existing[$row['date']]) ? 'duplicate' : 'new');
        }
        unset($row);

        $summary = collect($rows)->countBy('status')->all() + ['total' => count($rows)];
        if ($data['mode'] === 'preview') {
            return response()->json(['rows' => $rows, 'summary' => $summary]);
        }

        $overwrite = ($data['on_duplicate'] ?? 'skip') === 'overwrite';
        $result = DB::transaction(function () use ($rows, $existing, $overwrite, $country) {
            $created = $updated = 0;
            foreach ($rows as $row) {
                $values = ['name' => $row['name'], 'type' => $row['type'], 'recurring' => $row['recurring']];
                if ($row['status'] === 'new') {
                    NationalHoliday::create($values + ['country_code' => $country, 'date' => $row['date']]);
                    $created++;
                } elseif ($row['status'] === 'duplicate' && $overwrite) {
                    NationalHoliday::whereKey($existing[$row['date']])->update($values);
                    $updated++;
                }
            }

            return compact('created', 'updated');
        });
        Audit::log('national_holiday.imported', null, $result + ['country' => $country]);

        return response()->json(['status' => 'ok'] + $result + ['summary' => $summary]);
    }
}
