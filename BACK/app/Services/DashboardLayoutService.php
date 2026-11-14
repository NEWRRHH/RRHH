<?php

namespace App\Services;

use App\Models\CompanyDashboardLayout;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dashboard layout resolution (mixed model):
 *
 *   user's own arrangement  ->  company default (set by RRHH)  ->  built-in default
 *
 * A layout is 9 positions; each holds a widget key or null. The dashboard
 * fills them column by column (positions 1-3 = first column). A widget that
 * is not placed in any position is hidden. The welcome message always comes
 * from the company.
 */
class DashboardLayoutService
{
    public const POSITIONS = 9;

    /** Widget catalog in built-in default order. */
    public const WIDGETS = [
        'welcome' => 'Bienvenida',
        'birthdays' => 'Cumpleaños',
        'team' => 'Tu equipo',
        'vacation' => 'Vacaciones',
        'notifications' => 'Notificaciones',
        'worked_hours' => 'Horas trabajadas',
        'holidays' => 'Próximos festivos',
        'announcements' => 'Comunicados',
        'documents' => 'Documentos',
    ];

    /**
     * Cards that render a list, with what their number means:
     * 'max'     = how many items the card shows (birthdays);
     * 'visible' = how many items are visible before the list scrolls.
     */
    public const LIST_WIDGETS = [
        'notifications' => 'visible',
        'announcements' => 'visible',
        'birthdays' => 'max',
        'holidays' => 'visible',
        'team' => 'visible',
    ];

    public const DEFAULT_LIMIT = 5;

    public const MAX_LIMIT = 50;

    public const DEFAULT_WELCOME = [
        'title' => 'Bienvenido',
        'subtitle' => 'Aquí tienes un resumen de la actividad de la empresa.',
        'show_date' => true,
    ];

    /** @return array<int, array{key: string, name: string}> */
    public function catalog(): array
    {
        return array_map(fn ($key, $name) => ['key' => $key, 'name' => $name], array_keys(self::WIDGETS), self::WIDGETS);
    }

    /** @return array<int, ?string> */
    public function defaultSlots(): array
    {
        return array_pad(array_slice(array_keys(self::WIDGETS), 0, self::POSITIONS), self::POSITIONS, null);
    }

    /**
     * Company default (or built-in default when RRHH has not configured it).
     *
     * @return array{slots: array<int, ?string>, welcome: array, configured: bool, updated_at: ?string}
     */
    public function companyLayout(): array
    {
        $row = CompanyDashboardLayout::query()->first();

        return [
            'slots' => $row ? $this->sanitize($row->slots ?? []) : $this->defaultSlots(),
            'welcome' => $this->welcome($row?->settings['welcome'] ?? null),
            'limits' => $this->limits($row?->settings['limits'] ?? null),
            'configured' => (bool) $row,
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * What the user sees on their dashboard.
     */
    public function forUser(User $user): array
    {
        $company = $this->companyLayout();
        $own = $this->userSlots($user);

        return [
            'slots' => $own ?? $company['slots'],
            'source' => $own !== null ? 'user' : 'company',
            'company_slots' => $company['slots'],
            'settings' => ['welcome' => $company['welcome'], 'limits' => $company['limits']],
            'widgets' => $this->catalog(),
        ];
    }

    public function saveForUser(User $user, array $slots): void
    {
        $slots = $this->validateSlots($slots);

        DB::transaction(function () use ($user, $slots) {
            DashboardWidget::table()->where('user_id', $user->id)->delete();
            $now = now();
            $rows = [];
            foreach (array_keys(self::WIDGETS) as $key) {
                $index = array_search($key, $slots, true);
                $rows[] = [
                    'user_id' => $user->id,
                    'widget_key' => $key,
                    'slot_index' => $index === false ? null : $index,
                    'visible' => $index !== false,
                    'settings' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DashboardWidget::table()->insert($rows);
        });
    }

    /** Back to the company layout. */
    public function resetForUser(User $user): void
    {
        DashboardWidget::table()->where('user_id', $user->id)->delete();
    }

    public function saveCompanyLayout(User $editor, array $slots, array $welcome): array
    {
        $slots = $this->validateSlots($slots);
        $current = $this->companyLayout();

        CompanyDashboardLayout::query()->updateOrCreate([], [
            'slots' => $slots,
            // limits are edited in their own tab: keep them as they are
            'settings' => ['welcome' => $this->welcome($welcome), 'limits' => $current['limits']],
            'updated_by' => $editor->id,
        ]);

        return $this->companyLayout();
    }

    /**
     * Save how many items each list card shows (keeps slots and welcome).
     */
    public function saveCompanyLimits(User $editor, array $limits): array
    {
        $current = $this->companyLayout();

        CompanyDashboardLayout::query()->updateOrCreate([], [
            'slots' => $current['slots'],
            'settings' => ['welcome' => $current['welcome'], 'limits' => $this->limits($limits)],
            'updated_by' => $editor->id,
        ]);

        return $this->companyLayout();
    }

    /** @return array<int, array{key: string, name: string, mode: string}> */
    public function limitCatalog(): array
    {
        return array_map(
            fn ($key, $mode) => ['key' => $key, 'name' => self::WIDGETS[$key], 'mode' => $mode],
            array_keys(self::LIST_WIDGETS),
            self::LIST_WIDGETS,
        );
    }

    /**
     * Item limit per list card, clamped to 1..MAX_LIMIT (default DEFAULT_LIMIT).
     *
     * @return array<string, int>
     */
    public function limits(?array $input): array
    {
        $out = [];
        foreach (array_keys(self::LIST_WIDGETS) as $key) {
            $value = is_numeric($input[$key] ?? null) ? (int) $input[$key] : self::DEFAULT_LIMIT;
            $out[$key] = max(1, min(self::MAX_LIMIT, $value));
        }

        return $out;
    }

    /** @return array<int, ?string>|null */
    private function userSlots(User $user): ?array
    {
        $rows = DashboardWidget::table()->where('user_id', $user->id)->get(['widget_key', 'slot_index', 'visible']);
        if ($rows->isEmpty()) {
            return null;
        }

        $slots = array_fill(0, self::POSITIONS, null);
        foreach ($rows as $row) {
            $index = $row->slot_index === null ? null : (int) $row->slot_index;
            if (! $row->visible || $index === null || $index < 0 || $index >= self::POSITIONS) {
                continue;
            }
            if (isset(self::WIDGETS[$row->widget_key]) && $slots[$index] === null && ! in_array($row->widget_key, $slots, true)) {
                $slots[$index] = $row->widget_key;
            }
        }

        return $slots;
    }

    /**
     * Strict validation for user input: 9 positions, known widgets, no repeats.
     *
     * @return array<int, ?string>
     */
    private function validateSlots(array $slots): array
    {
        $slots = array_values(array_slice(array_pad($slots, self::POSITIONS, null), 0, self::POSITIONS));
        $seen = [];
        foreach ($slots as $i => $key) {
            if ($key === null || $key === '') {
                $slots[$i] = null;
                continue;
            }
            if (! is_string($key) || ! isset(self::WIDGETS[$key])) {
                throw ValidationException::withMessages(['slots' => 'Tarjeta desconocida.']);
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['slots' => 'Una tarjeta no puede estar en dos posiciones.']);
            }
            $seen[$key] = true;
        }

        return $slots;
    }

    /**
     * Lenient cleanup for stored data (drops unknown or repeated widgets).
     *
     * @return array<int, ?string>
     */
    private function sanitize(array $slots): array
    {
        $out = array_fill(0, self::POSITIONS, null);
        foreach (array_slice(array_values($slots), 0, self::POSITIONS) as $i => $key) {
            if (is_string($key) && isset(self::WIDGETS[$key]) && ! in_array($key, $out, true)) {
                $out[$i] = $key;
            }
        }

        return $out;
    }

    private function welcome(?array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $subtitle = trim((string) ($input['subtitle'] ?? ''));

        return [
            'title' => mb_substr($title !== '' ? $title : self::DEFAULT_WELCOME['title'], 0, 80),
            'subtitle' => mb_substr($subtitle !== '' ? $subtitle : self::DEFAULT_WELCOME['subtitle'], 0, 180),
            'show_date' => array_key_exists('show_date', (array) $input) ? (bool) $input['show_date'] : true,
        ];
    }
}
