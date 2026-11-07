<?php

namespace App\Support;

use App\Models\ErrorLog;
use App\Support\Tenancy\Tenant;
use Throwable;

/**
 * Stores reported exceptions in error_logs so the super admin can see them.
 *
 * Called from the exception handler (bootstrap/app.php) only for reportable
 * exceptions: validation, auth, 404 and other HTTP exceptions are skipped by
 * Laravel already. It must never throw (that would hide the real error).
 */
final class ErrorLogRecorder
{
    private static bool $recording = false;

    public static function record(Throwable $e): void
    {
        // avoid recursion if writing the log itself fails
        if (self::$recording) {
            return;
        }
        self::$recording = true;

        try {
            // artisan commands/queues have no real HTTP request
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            $file = self::relativePath($e->getFile());
            $fingerprint = hash('sha256', get_class($e) . '|' . $file . '|' . $e->getLine() . '|' . mb_substr($e->getMessage(), 0, 300));

            $open = ErrorLog::query()
                ->where('fingerprint', $fingerprint)
                ->whereNull('resolved_at')
                ->first();

            if ($open) {
                $open->increment('occurrences', 1, ['last_seen_at' => now()]);

                return;
            }

            ErrorLog::create([
                'fingerprint' => $fingerprint,
                'company_id' => Tenant::id() ?? $request?->user()?->company_id,
                'user_id' => $request?->user()?->id,
                'level' => 'error',
                'exception' => get_class($e),
                'message' => mb_substr($e->getMessage() ?: '(sin mensaje)', 0, 5000),
                'file' => $file,
                'line' => $e->getLine(),
                'method' => $request?->method(),
                'url' => $request ? mb_substr($request->fullUrl(), 0, 2048) : 'console',
                'ip' => $request?->ip(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 60000),
                'last_seen_at' => now(),
            ]);
        } catch (Throwable) {
            // the database may be the thing that is down: the file log still has it
        } finally {
            self::$recording = false;
        }
    }

    private static function relativePath(string $path): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
