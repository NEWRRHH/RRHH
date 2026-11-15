<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Payslips (nóminas) are dropped by RRHH / the payroll provider in
 *
 *   companies/{company slug}/nominas/{employee folder}/<file>.pdf
 *
 * on FILES_DISK. `php artisan files:folders` (and every new employee) creates
 * the employee folder as "Juan Pérez - 30111222" (name - DNI, or name - id when
 * there is no DNI). A folder named only with the full name, DNI, email,
 * username or id also works; accents, case and separators don't matter.
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

    public static function fullName(User $user): string
    {
        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $full !== '' ? $full : (string) ($user->name ?: $user->email);
    }

    /** "Juan Pérez - 30111222" (DNI) or "Juan Pérez - 15" (id). */
    public static function folderNameFor(User $user): string
    {
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], ' ', self::fullName($user));
        $id = trim((string) $user->dni) !== '' ? trim((string) $user->dni) : (string) $user->id;

        return trim(preg_replace('/\s+/', ' ', $name)) . ' - ' . $id;
    }

    /** Unique identifiers of a person (normalised): DNI, email, username, id. */
    private function idKeys(User $user): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => self::normalize((string) $v),
            [$user->dni, $user->email, $user->username, (string) $user->id],
        )));
    }

    /** Every name a folder may have for this person (normalised). */
    private function allKeys(User $user): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [self::normalize(self::fullName($user)), self::normalize((string) $user->name), self::normalize(self::folderNameFor($user))],
            $this->idKeys($user),
        ))));
    }

    /**
     * Who a folder belongs to: the part after the last " - " (DNI / id / email)
     * wins; otherwise the whole name. Null when nobody or several people match.
     *
     * @param  Collection<int, User>  $users
     * @return array{0: ?User, 1: 'ok'|'none'|'ambiguous'}
     */
    public function owner(string $folder, Collection $users): array
    {
        $name = basename($folder);
        if (str_contains($name, ' - ')) {
            $tail = self::normalize(Str::afterLast($name, ' - '));
            $byId = $users->filter(fn (User $u) => in_array($tail, $this->idKeys($u), true));
            if ($byId->count() === 1) {
                return [$byId->first(), 'ok'];
            }
        }

        $whole = self::normalize($name);
        $matches = $users->filter(fn (User $u) => in_array($whole, $this->allKeys($u), true));

        return match ($matches->count()) {
            1 => [$matches->first(), 'ok'],
            0 => [null, 'none'],
            default => [null, 'ambiguous'],
        };
    }

    private function companyUsers(int $companyId): Collection
    {
        return User::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at')->get();
    }

    /**
     * Register the new files of one employee (called when they open Documentos).
     */
    public function syncUser(User $user): int
    {
        if (! $user->company_id) {
            return 0;
        }
        $users = $this->companyUsers($user->company_id);
        $created = 0;
        foreach (FileStorage::disk()->directories(FileStorage::folder($user->company_id, FileStorage::PAYROLL)) as $dir) {
            [$owner] = $this->owner($dir, $users);
            if ($owner && $owner->id === $user->id) {
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
        $users = $this->companyUsers($companyId);
        $created = 0;
        $unmatched = [];
        $ambiguous = [];
        foreach (FileStorage::disk()->directories(FileStorage::folder($companyId, FileStorage::PAYROLL)) as $dir) {
            [$owner, $status] = $this->owner($dir, $users);
            if ($owner) {
                $created += $this->registerFolder($dir, $owner);
            } elseif ($status === 'ambiguous') {
                $ambiguous[] = $dir;
            } else {
                $unmatched[] = $dir;
            }
        }

        return compact('created', 'unmatched', 'ambiguous');
    }

    /**
     * Creates the payroll folder of an employee unless one already points to
     * them. Returns the folder path.
     */
    public function ensureEmployeeFolder(User $user, ?Collection $users = null): ?string
    {
        if (! $user->company_id) {
            return null;
        }
        $users ??= $this->companyUsers($user->company_id);
        $root = FileStorage::folder($user->company_id, FileStorage::PAYROLL);
        foreach (FileStorage::disk()->directories($root) as $dir) {
            [$owner] = $this->owner($dir, $users);
            if ($owner && $owner->id === $user->id) {
                return $dir;
            }
        }

        $dir = $root . '/' . self::folderNameFor($user);
        FileStorage::disk()->makeDirectory($dir);

        return $dir;
    }

    /**
     * The whole tree of a company: documents, chat, nominas (+ one folder per
     * active employee) on FILES_DISK and avatars on PUBLIC_FILES_DISK.
     *
     * @return array<int, string> folders created / already present
     */
    public function ensureCompanyFolders(int $companyId): array
    {
        $done = [];
        foreach ([FileStorage::DOCUMENTS, FileStorage::CHAT, FileStorage::PAYROLL] as $area) {
            $dir = FileStorage::folder($companyId, $area);
            FileStorage::disk()->makeDirectory($dir);
            $done[] = $dir;
        }
        $avatars = FileStorage::folder($companyId, FileStorage::AVATARS);
        FileStorage::publicDisk()->makeDirectory($avatars);
        $done[] = $avatars;

        $users = $this->companyUsers($companyId);
        foreach ($users->whereNull('disabled_at') as $user) {
            $done[] = $this->ensureEmployeeFolder($user, $users);
        }

        return array_values(array_filter($done));
    }

    private function registerFolder(string $dir, User $user): int
    {
        $disk = FileStorage::disk();
        $known = Document::withoutGlobalScopes()->where('user_id', $user->id)->where('category', 'payroll')->pluck('filename')->flip();

        $created = 0;
        foreach ($disk->files($dir) as $path) {
            $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
            if (isset($known[$path]) || ! in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }
            Document::withoutGlobalScopes()->insert([
                'company_id' => $user->company_id,
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
