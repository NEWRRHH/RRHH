<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\NationalHoliday;
use App\Services\HolidayService;
use App\Support\Audit;
use App\Support\HolidayCsvParser;
use App\Support\Tenancy\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Company holidays (festivos): calendar CRUD, CSV import and copy from the
 * national holidays catalog. Reading the upcoming ones is open to everybody;
 * managing them needs "holidays.manage".
 */
class HolidayController extends Controller
{
    private const TYPES = ['national', 'regional', 'company'];

    public function __construct(private HolidayService $holidays)
    {
    }

    /** Next holidays for the dashboard card. */
    public function upcoming()
    {
        return response()->json($this->holidays->upcoming(5));
    }

    public function index(Request $request)
    {
        if ($request->user()->cannot('holidays.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $year = (int) ($request->query('year') ?: Carbon::today()->year);
        $year = max(2000, min(2100, $year));
        $start = Carbon::create($year, 1, 1);
        $end = Carbon::create($year, 12, 31);

        $rows = Holiday::query()
            ->where(fn ($q) => $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orWhere('recurring', true))
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'type', 'recurring'])
            ->map(function (Holiday $h) use ($year) {
                // recurring holidays are shown on this year's date
                $date = $h->date->copy();
                if ($h->recurring && checkdate($date->month, $date->day, $year)) {
                    $date->year($year);
                }

                return [
                    'id' => $h->id,
                    'date' => $date->toDateString(),
                    'original_date' => $h->date->toDateString(),
                    'name' => $h->name,
                    'type' => $h->type,
                    'recurring' => $h->recurring,
                ];
            })
            ->filter(fn ($h) => str_starts_with($h['date'], (string) $year))
            ->sortBy('date')
            ->values();

        return response()->json([
            'year' => $year,
            'holidays' => $rows,
            'country_code' => $request->user()->company?->country_code,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('holidays.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate($this->rules());
        $data = array_filter($data, fn ($v) => $v !== null);
        $holiday = Holiday::create($data + ['type' => 'company', 'recurring' => false]);
        Audit::log('holiday.created', $holiday, ['date' => $holiday->date->toDateString(), 'name' => $holiday->name]);

        return response()->json(['status' => 'ok', 'holiday' => $holiday], 201);
    }

    public function update(Request $request, int $id)
    {
        if ($request->user()->cannot('holidays.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $holiday = Holiday::findOrFail($id);
        $data = $request->validate($this->rules($holiday->id));
        $holiday->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['status' => 'ok', 'holiday' => $holiday]);
    }

    public function destroy(Request $request, int $id)
    {
        if ($request->user()->cannot('holidays.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $holiday = Holiday::findOrFail($id);
        $holiday->delete();
        Audit::log('holiday.deleted', $holiday, ['date' => $holiday->date->toDateString(), 'name' => $holiday->name]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * CSV import in two steps with the same file:
     *   mode=preview -> rows with their status (new / duplicate / error)
     *   mode=commit  -> writes the valid rows (duplicates skipped or overwritten)
     */
    public function import(Request $request)
    {
        if ($request->user()->cannot('holidays.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'file' => 'required|file|max:1024|mimes:csv,txt',
            'mode' => 'required|in:preview,commit',
            'on_duplicate' => 'nullable|in:skip,overwrite',
        ]);

        $rows = HolidayCsvParser::parse($request->file('file'));
        $existing = Holiday::query()->pluck('id', 'date')->mapWithKeys(fn ($id, $date) => [Carbon::parse($date)->toDateString() => $id]);

        foreach ($rows as &$row) {
            $row['status'] = $row['error'] ? 'error' : (isset($existing[$row['date']]) ? 'duplicate' : 'new');
        }
        unset($row);

        $summary = [
            'total' => count($rows),
            'new' => count(array_filter($rows, fn ($r) => $r['status'] === 'new')),
            'duplicate' => count(array_filter($rows, fn ($r) => $r['status'] === 'duplicate')),
            'error' => count(array_filter($rows, fn ($r) => $r['status'] === 'error')),
        ];

        if ($data['mode'] === 'preview') {
            return response()->json(['rows' => $rows, 'summary' => $summary]);
        }

        $overwrite = ($data['on_duplicate'] ?? 'skip') === 'overwrite';
        $result = DB::transaction(function () use ($rows, $existing, $overwrite) {
            $created = $updated = 0;
            foreach ($rows as $row) {
                $values = ['name' => $row['name'], 'type' => $row['type'], 'recurring' => $row['recurring']];
                if ($row['status'] === 'new') {
                    Holiday::create($values + ['date' => $row['date']]);
                    $created++;
                } elseif ($row['status'] === 'duplicate' && $overwrite) {
                    Holiday::whereKey($existing[$row['date']])->update($values);
                    $updated++;
                }
            }

            return compact('created', 'updated');
        });

        Audit::log('holiday.imported', null, $result + ['skipped' => $summary['total'] - $result['created'] - $result['updated']]);

        return response()->json(['status' => 'ok'] + $result + ['summary' => $summary]);
    }

    /** CSV template with the expected columns. */
    public function template()
    {
        $year = Carbon::today()->year + 1;
        $csv = "\xEF\xBB\xBFfecha;nombre;tipo;recurrente\n"
            . "01/01/{$year};Año Nuevo;nacional;si\n"
            . "06/01/{$year};Reyes;nacional;si\n"
            . "15/05/{$year};Fiesta local;regional;no\n"
            . "24/12/{$year};Cierre de empresa;empresa;no\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_festivos.csv"',
        ]);
    }

    /**
     * Copy the platform's national holidays of a country/year into the company.
     */
    public function importNational(Request $request)
    {
        if ($request->user()->cannot('holidays.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'country_code' => 'required|string|size:2',
            'year' => 'required|integer|min:2000|max:2100',
        ]);
        $country = strtoupper($data['country_code']);
        $year = (int) $data['year'];

        $catalog = NationalHoliday::where('country_code', $country)
            ->where(fn ($q) => $q->whereYear('date', $year)->orWhere('recurring', true))
            ->get();
        $existing = Holiday::query()->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip();

        $created = 0;
        foreach ($catalog as $national) {
            $date = $national->date->copy();
            if ($national->recurring) {
                if (! checkdate($date->month, $date->day, $year)) {
                    continue;
                }
                $date->year($year);
            }
            if (isset($existing[$date->toDateString()])) {
                continue;
            }
            Holiday::create([
                'date' => $date->toDateString(),
                'name' => $national->name,
                'type' => $national->type,
                'recurring' => false,
            ]);
            $existing[$date->toDateString()] = true;
            $created++;
        }

        Audit::log('holiday.imported_national', null, ['country' => $country, 'year' => $year, 'created' => $created]);

        return response()->json(['status' => 'ok', 'created' => $created, 'available' => $catalog->count()]);
    }

    private function rules(?int $ignoreId = null): array
    {
        $unique = TenantRule::unique('holidays', 'date');
        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return [
            'date' => ['required', 'date_format:Y-m-d', $unique],
            'name' => 'required|string|max:150',
            'type' => 'nullable|in:' . implode(',', self::TYPES),
            'recurring' => 'nullable|boolean',
        ];
    }
}
