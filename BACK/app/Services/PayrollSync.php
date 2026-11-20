<?php

namespace App\Services;

use App\Events\DocumentsUpdated;
use App\Http\Controllers\Api\DocumentController;
use App\Models\Document;
use App\Models\PayrollBatchFile;
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
 * the employee folder named with the DNI ("30111222A"), the same key the
 * gestoría uses for the payslips; older "Name - DNI" folders are moved into it.
 * A folder named with the full name, email, username or id is also recognised;
 * accents, case and separators don't matter.
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

    /**
     * Folder of an employee: their DNI ("30111222A"), the same key the
     * gestoría uses to name the payslips. Without DNI: "sin-dni-{id}".
     */
    public static function folderNameFor(User $user): string
    {
        $dni = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $user->dni));

        return $dni !== '' ? $dni : 'sin-dni-' . $user->id;
    }

    /** Unique identifiers of a person (normalised): DNI, email, username, id. */
    private function idKeys(User $user): array
    {
        // the DNI only by its letters/digits: "30.111.222-a" == "30111222A"
        $dni = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $user->dni));

        return array_values(array_filter(array_merge(
            [$dni],
            array_map(fn ($v) => self::normalize((string) $v), [$user->email, $user->username, (string) $user->id]),
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
        // a DNI may be written "30.111.222-A": also compare letters/digits only
        $variants = fn (string $v) => array_unique([self::normalize($v), strtolower(preg_replace('/[^A-Za-z0-9]/', '', $v))]);

        if (str_contains($name, ' - ')) {
            $tail = $variants(Str::afterLast($name, ' - '));
            $byId = $users->filter(fn (User $u) => (bool) array_intersect($tail, $this->idKeys($u)));
            if ($byId->count() === 1) {
                return [$byId->first(), 'ok'];
            }
        }

        $whole = $variants($name);
        $matches = $users->filter(fn (User $u) => (bool) array_intersect($whole, $this->allKeys($u)));

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
        $this->pruneMissing($user->company_id, $user->id);
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
     * @return array{created: int, removed: int, unmatched: array<int, string>, ambiguous: array<int, string>}
     */
    public function syncCompany(int $companyId): array
    {
        $removed = $this->pruneMissing($companyId);
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

        return compact('created', 'removed', 'unmatched', 'ambiguous');
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
        $target = $root . '/' . self::folderNameFor($user);
        $disk = FileStorage::disk();

        // older folders of this person ("Juan Pérez - 30111222", or created
        // by hand): their files move into the DNI folder
        foreach ($disk->directories($root) as $dir) {
            if ($dir === $target) {
                continue;
            }
            [$owner] = $this->owner($dir, $users);
            if ($owner && $owner->id === $user->id && trim((string) $user->dni) !== '') {
                $this->moveFolder($dir, $target);
            } elseif ($owner && $owner->id === $user->id) {
                return $dir; // no DNI yet: keep using the existing folder
            }
        }

        $disk->makeDirectory($target);

        return $target;
    }

    /**
     * Moves every file of a folder into another one and updates the paths
     * stored in the DB (documents, payroll batches). Removes the old folder.
     */
    private function moveFolder(string $from, string $to): void
    {
        $disk = FileStorage::disk();
        $disk->makeDirectory($to);
        foreach ($disk->allFiles($from) as $old) {
            $new = $to . '/' . basename($old);
            for ($i = 2; $disk->exists($new); $i++) {
                $new = $to . '/' . pathinfo($old, PATHINFO_FILENAME) . '-' . $i . '.' . pathinfo($old, PATHINFO_EXTENSION);
            }
            $disk->move($old, $new);
            Document::withoutGlobalScopes()->where('filename', $old)->update(['filename' => $new]);
            PayrollBatchFile::withoutGlobalScopes()->where('path', $old)->update(['path' => $new]);
        }
        $disk->deleteDirectory($from);
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

    /**
     * The folder is the source of truth: a payslip whose file was removed
     * from it disappears from Documentos too (soft delete, recoverable).
     * Skipped when the payroll root itself is missing (disk not mounted /
     * wrong FILES_DISK), so a storage problem never hides every payslip.
     *
     * @return int documents removed
     */
    public function pruneMissing(int $companyId, ?int $userId = null): int
    {
        $disk = FileStorage::disk();
        if (! $disk->directoryExists(FileStorage::folder($companyId, FileStorage::PAYROLL))) {
            return 0;
        }

        $rows = Document::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('category', 'payroll')
            ->whereNull('deleted_at')
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->get(['id', 'user_id', 'filename']);

        $affected = [];
        $removed = 0;
        foreach ($rows as $row) {
            if ($row->filename && ! $disk->exists($row->filename)) {
                Document::withoutGlobalScopes()->whereKey($row->id)->update(['deleted_at' => now()]);
                $affected[$row->user_id] = true;
                $removed++;
            }
        }

        foreach (array_keys($affected) as $id) {
            try {
                event(new DocumentsUpdated((int) $id, DocumentController::unreadCount((int) $id)));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $removed;
    }

    private function registerFolder(string $dir, User $user): int
    {
        $disk = FileStorage::disk();
        $rows = Document::withoutGlobalScopes()->where('user_id', $user->id)->where('category', 'payroll')->get(['id', 'filename', 'deleted_at']);
        $known = $rows->whereNull('deleted_at')->pluck('filename')->flip();
        // removed earlier (file deleted) and now back in the folder: restore as new
        $removed = $rows->whereNotNull('deleted_at')->pluck('id', 'filename');

        $created = 0;
        foreach ($disk->files($dir) as $path) {
            $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
            if (isset($known[$path]) || ! in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }
            if (isset($removed[$path])) {
                Document::withoutGlobalScopes()->whereKey($removed[$path])->update([
                    'deleted_at' => null,
                    'read_at' => null,
                    'size' => (int) $disk->size($path),
                    'updated_at' => now(),
                ]);
                $created++;
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
                'read_at' => null, // new: badge until the employee opens Nóminas
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        // real-time badge in the Documentos menu / card of the employee
        if ($created) {
            try {
                event(new DocumentsUpdated($user->id, DocumentController::unreadCount($user->id), $created));
            } catch (\Throwable $e) {
                report($e); // websocket down: the badge still shows on next load
            }
        }

        return $created;
    }
}
