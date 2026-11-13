<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * Parses a holidays CSV:
 *
 *   fecha;nombre;tipo;recurrente
 *   25/12/2026;Navidad;nacional;si
 *
 * - delimiter ";" or "," (auto-detected), optional UTF-8 BOM, optional header
 * - fecha: DD/MM/AAAA, DD-MM-AAAA or AAAA-MM-DD
 * - tipo (optional): nacional | regional | empresa  (also national/company)
 * - recurrente (optional): si/no, 1/0, true/false
 *
 * Every row comes back with either normalized values or an error message,
 * so the UI can show a preview before importing.
 */
final class HolidayCsvParser
{
    public const MAX_ROWS = 500;

    private const TYPES = [
        'nacional' => 'national', 'national' => 'national',
        'regional' => 'regional', 'autonomico' => 'regional', 'autonómico' => 'regional', 'local' => 'regional',
        'empresa' => 'company', 'company' => 'company',
    ];

    /**
     * @param  array<int, string>  $allowedTypes
     * @return array<int, array{line: int, date: ?string, name: ?string, type: ?string, recurring: bool, error: ?string}>
     */
    public static function parse(UploadedFile $file, array $allowedTypes = ['national', 'regional', 'company']): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $delimiter = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';

        $rows = [];
        $seen = [];
        foreach ($lines as $index => $raw) {
            $lineNumber = $index + 1;
            if (trim($raw) === '') {
                continue;
            }
            $cells = array_map(fn ($c) => trim((string) $c), str_getcsv($raw, $delimiter));

            // header row: first cell is not a date
            if ($index === 0 && self::parseDate($cells[0] ?? '') === null && preg_match('/fecha|date/i', $cells[0] ?? '')) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                $rows[] = self::row($lineNumber, null, null, null, false, 'Se superó el máximo de ' . self::MAX_ROWS . ' filas');
                break;
            }

            $date = self::parseDate($cells[0] ?? '');
            $name = mb_substr($cells[1] ?? '', 0, 150);
            $typeRaw = mb_strtolower($cells[2] ?? '');
            $type = $typeRaw === '' ? $allowedTypes[0] : (self::TYPES[$typeRaw] ?? null);
            $recurring = in_array(mb_strtolower($cells[3] ?? ''), ['si', 'sí', 'yes', '1', 'true', 'x'], true);

            $error = match (true) {
                $date === null => 'Fecha no válida (usa DD/MM/AAAA o AAAA-MM-DD)',
                $name === '' => 'Falta el nombre',
                $type === null || ! in_array($type, $allowedTypes, true) => 'Tipo no válido',
                isset($seen[$date]) => 'Fecha repetida en el archivo (línea ' . $seen[$date] . ')',
                default => null,
            };
            if ($date !== null && ! isset($seen[$date])) {
                $seen[$date] = $lineNumber;
            }

            $rows[] = self::row($lineNumber, $date, $name ?: null, $type, $recurring, $error);
        }

        return $rows;
    }

    private static function parseDate(string $value): ?string
    {
        $value = trim($value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) {
                return Carbon::instance($date)->toDateString();
            }
        }

        return null;
    }

    private static function row(int $line, ?string $date, ?string $name, ?string $type, bool $recurring, ?string $error): array
    {
        return compact('line', 'date', 'name', 'type', 'recurring', 'error');
    }
}
