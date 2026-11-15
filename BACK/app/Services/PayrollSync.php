<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Support\Str;

/**
 * Payslips (nóminas) are dropped by RRHH / the payroll provider in
 *
 *   companies/{company id}/nominas/{employee folder}/<file>.pdf
 *
 * on FILES_DISK. The employee folder can be named after the employee's full
 * name ("Juan Pérez"), DNI, email, username or user id; accents, case and
 * separators don't matter ("juan_perez", "JUAN PEREZ" and "Juan Pérez" match).
 * Every new file becomes a "payroll" document of that employee, so it shows in
 * Documentos > Nóminas. Files already registered are skipped.
 */
class PayrollSync
{
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx'];

    public static function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9@.]+/', ' ', Str::lower(Str::ascii($value))));
    }

    /** Folder names that identify this employee (normalised). */
    public function keysFor(User $user): array
    {
        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return array_values(array_filter(array_unique(array_map(
            fn ($v) => self::normalize((string) $v),
            [$full, $user->name, $user->dni, $user->email, $user->username, (string) $user->id],
        ))));
    }

    private function root(int $companyId): string
    {
        return FileStorage::folder($companyId, FileStorage::PAYROLL);
    }

    /**
     * Register the new files of one employee (called when they open Documentos).
     */
    public function syncUser(User $user): int
    {
        if (! $user->company_id) {
            return 0;
        }
        $keys = $this->keysFor($user);
        $created = 0;
        foreach (FileStorage::disk()->directories($this->root($user->company_id)) as $dir) {
            if (in_array(self::normalize(basename($dir)), $keys, true)) {
                $created += $this->registerFolder($dir, $user);
            }
        }

        return $created;
    }

    /**
     * Whole company (artisan nominas:sync). Folders that match nobody, or
     * more than one person, are reported and left untouched.
     *
     * @return array{created: int, unmatched: array<int, string>, ambiguous: array<int, string>}
     */
    public function syncCompany(int $companyId): array
    {
        $users = User::query()->whereNull('disabled_at')->get();
        $index = [];
        foreach ($users as $user) {
            foreach ($this->keysFor($user) as $key) {
                $index[$key][$user->id] = $user;
            }
        }

        $created = 0;
        $unmatched = [];
        $ambiguous = [];
        foreach (FileStorage::disk()->directories($this->root($companyId)) as $dir) {
            $matches = $index[self::normalize(basename($dir))] ?? [];
            if (count($matches) === 1) {
                $created += $this->registerFolder($dir, reset($matches));
            } elseif (count($matches) > 1) {
                $ambiguous[] = $dir;
            } else {
                $unmatched[] = $dir;
            }
        }

        return compact('created', 'unmatched', 'ambiguous');
    }

    private function registerFolder(string $dir, User $user): int
    {
        $disk = FileStorage::disk();
        $known = Document::table()->where('user_id', $user->id)->where('category', 'payroll')->pluck('filename')->flip();

        $created = 0;
        foreach ($disk->files($dir) as $path) {
            $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
            if (isset($known[$path]) || ! in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }
            Document::table()->insert([
                'description' => pathinfo($path, PATHINFO_FILENAME),
                'user_id' => $user->id,
                'filename' => $path,
                'original_name' => basename($path),
                'mime_type' => $disk->mimeType($path) ?: null,
                'size' => (int) $disk->size($path),
                'visibility' => true,
                'document_type_id' => null,
                'category' => 'payroll',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }
}
