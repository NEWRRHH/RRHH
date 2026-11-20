<?php

namespace App\Services;

use App\Events\DocumentsUpdated;
use App\Http\Controllers\Api\DocumentController;
use App\Mail\AttendanceSummaryMail;
use App\Models\Document;
use App\Models\User;
use App\Support\FileStorage;
use App\Support\Tenancy\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Builds the monthly attendance summary PDF of an employee, keeps it in
 * Documentos > Asistencia (companies/{slug}/asistencia/{DNI}/...) and,
 * optionally, emails it to the employee. Generating the same month again
 * replaces the file and marks it as new.
 */
class AttendanceSummaryPublisher
{
    public const CATEGORY = 'attendance';

    /** Renders the PDF (binary string). */
    public function render(User $user, Carbon $month): string
    {
        return Tenant::run($user->company_id, function () use ($user, $month) {
            // fresh service per employee/company: its holiday cache is per company
            $data = app()->make(AttendanceSummaryService::class)->build($user, $month);

            return Pdf::loadView('pdf.attendance-summary', $data)
                ->setPaper('a4')
                // embed only the glyphs used (~900 KB -> tens of KB per PDF)
                ->setOption('isFontSubsettingEnabled', true)
                ->output();
        });
    }

    public static function fileName(Carbon $month): string
    {
        return 'resumen-asistencia-' . $month->format('Y-m') . '.pdf';
    }

    /**
     * @return array{document_id: int, emailed: bool}
     */
    public function publish(User $user, Carbon $month, bool $email): array
    {
        $month = $month->copy()->startOfMonth();
        $pdf = $this->render($user, $month);

        return Tenant::run($user->company_id, function () use ($user, $month, $pdf, $email) {
            $path = $this->pathFor($user, $month);
            FileStorage::disk()->put($path, $pdf);
            $this->handOverToWebUser($user);

            $label = AttendanceSummaryService::monthLabel($month);
            $values = [
                'description' => "Resumen de asistencia de {$label}",
                'original_name' => self::fileName($month),
                'mime_type' => 'application/pdf',
                'size' => strlen($pdf),
                'visibility' => true,
                'read_at' => null, // new: badge until the employee opens the tab
                'deleted_at' => null,
                'updated_at' => now(),
            ];

            $existing = Document::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('category', self::CATEGORY)
                ->where('filename', $path)
                ->value('id');
            if ($existing) {
                Document::withoutGlobalScopes()->whereKey($existing)->update($values);
                $id = (int) $existing;
            } else {
                $id = (int) Document::withoutGlobalScopes()->insertGetId($values + [
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'filename' => $path,
                    'document_type_id' => null,
                    'category' => self::CATEGORY,
                    'created_at' => now(),
                ]);
            }

            try {
                event(new DocumentsUpdated($user->id, DocumentController::unreadCount($user->id), 1));
            } catch (\Throwable $e) {
                report($e); // websocket down: the badge shows on next load
            }

            $emailed = false;
            if ($email && filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($user->email)->queue(new AttendanceSummaryMail(
                        employeeName: PayrollSync::fullName($user),
                        companyName: (string) ($user->company?->name ?? ''),
                        monthLabel: $label,
                        disk: FileStorage::diskName(),
                        path: $path,
                        fileName: self::fileName($month),
                    ));
                    $emailed = true;
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return ['document_id' => $id, 'emailed' => $emailed];
        });
    }

    private function pathFor(User $user, Carbon $month): string
    {
        return FileStorage::folder($user->company_id, FileStorage::ATTENDANCE, PayrollSync::folderNameFor($user)) . '/' . self::fileName($month);
    }

    /** The employee already has the summary of that month (not deleted). */
    public function exists(User $user, Carbon $month): bool
    {
        return Document::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('category', self::CATEGORY)
            ->where('original_name', self::fileName($month->copy()->startOfMonth()))
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * The scheduler (or a manual `docker exec ... artisan`) may run as root:
     * the folders it creates would be root-only (0700) and PHP-FPM
     * (www-data) could not serve the PDF -> 404 on download. Give them to
     * the web user like the rest of storage/.
     */
    private function handOverToWebUser(User $user): void
    {
        if (FileStorage::diskName() !== 'local' || ! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }
        $web = posix_getpwnam('www-data');
        if (! $web) {
            return;
        }

        $dir = FileStorage::disk()->path(FileStorage::folder($user->company_id, FileStorage::ATTENDANCE));
        if (! is_dir($dir)) {
            return;
        }
        $paths = [$dir];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $paths[] = $item->getPathname();
        }
        foreach ($paths as $path) {
            @chown($path, $web['uid']);
            @chgrp($path, $web['gid']);
            @chmod($path, is_dir($path) ? 0775 : 0664);
        }
    }

    /**
     * Employees that get a summary of that month: active, hired before the
     * month ends.
     */
    public function recipients(int $companyId, Carbon $month, ?array $userIds = null)
    {
        return User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNull('disabled_at')
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $month->copy()->endOfMonth()->toDateString()))
            ->when($userIds, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();
    }
}
