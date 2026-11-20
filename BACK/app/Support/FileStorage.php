<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single place that decides WHERE uploaded files are stored.
 *
 * - The disks come from .env (config/filesystems.php): FILES_DISK for private
 *   files, PUBLIC_FILES_DISK for profile photos. Moving to S3 / R2 / MinIO is
 *   a matter of changing those variables (+ `php artisan files:move`).
 * - Every file lives under its company, named by the company slug:
 *   companies/{slug}/{area}/... (e.g. companies/nano-tech/nominas/...), so a
 *   company's files can be found, backed up or deleted together. The full
 *   path is stored in the DB, so existing files are never lost.
 * - ensureCompanyFolders() creates the whole tree up front (artisan
 *   files:folders, new companies and new employees).
 */
final class FileStorage
{
    public const DOCUMENTS = 'documents';
    public const CHAT = 'chat';
    public const AVATARS = 'avatars';
    /** payslips dropped per employee: companies/{id}/nominas/{employee}/ (see PayrollSync) */
    public const PAYROLL = 'nominas';
    /** monthly attendance summaries (PDF): companies/{slug}/asistencia/{employee}/ */
    public const ATTENDANCE = 'asistencia';

    public static function diskName(): string
    {
        return (string) config('filesystems.files_disk', 'local');
    }

    public static function publicDiskName(): string
    {
        return (string) config('filesystems.public_files_disk', 'public');
    }

    /** Private disk: documents and chat attachments. */
    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function publicDisk(): Filesystem
    {
        return Storage::disk(self::publicDiskName());
    }

    /** @var array<int, string> company id => folder name */
    private static array $companyFolders = [];

    /**
     * Folder of a company: its slug ("nano-tech"), fixed and unique since the
     * company was created; the id only if it has no slug.
     */
    public static function companyFolder(?int $companyId): string
    {
        if (! $companyId) {
            return 'platform';
        }

        return self::$companyFolders[$companyId] ??= (string) (Company::withTrashed()->whereKey($companyId)->value('slug') ?: $companyId);
    }

    /** companies/{slug}/{area}[/sub] ("platform" for users without company). */
    public static function folder(?int $companyId, string $area, string $sub = ''): string
    {
        $base = 'companies/' . self::companyFolder($companyId) . '/' . $area;

        return $sub !== '' ? $base . '/' . trim($sub, '/') : $base;
    }

    /**
     * Stores a private file with a random name; returns the path to keep in the DB.
     */
    public static function storePrivate(UploadedFile $file, ?int $companyId, string $area, string $sub = ''): string
    {
        $name = Str::random(32) . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin');

        return $file->storeAs(self::folder($companyId, $area, $sub), $name, self::diskName());
    }

    /**
     * Stores a profile photo and returns the URL to keep in users.photo.
     * The local "public" disk keeps the old relative "/storage/..." form
     * (the frontend prefixes the API host).
     */
    public static function storeAvatar(UploadedFile $file, ?int $companyId): string
    {
        $path = $file->storePublicly(self::folder($companyId, self::AVATARS), self::publicDiskName());

        return self::avatarUrl($path);
    }

    /** company logo (sidebar + favicon), public: companies/{slug}/branding/ */
    public const BRANDING = 'branding';

    public const LOGO_MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    public const LOGO_MAX_BYTES = 1024 * 1024;

    /**
     * Validates and stores a company logo (raw bytes, from an upload or a
     * data: URL) and returns its public URL. SVG is not accepted: served
     * from our domain it could carry scripts.
     *
     * @throws \InvalidArgumentException with a message for the user
     */
    public static function storeCompanyLogo(string $bytes, int $companyId): string
    {
        $mime = self::assertValidLogo($bytes);
        $path = self::folder($companyId, self::BRANDING) . '/logo-' . Str::random(12) . '.' . self::LOGO_MIMES[$mime];
        self::publicDisk()->put($path, $bytes, 'public');

        return self::avatarUrl($path);
    }

    /**
     * @return string the mime type
     * @throws \InvalidArgumentException
     */
    public static function assertValidLogo(string $bytes): string
    {
        if ($bytes === '' || strlen($bytes) > self::LOGO_MAX_BYTES) {
            throw new \InvalidArgumentException('El logo debe pesar como máximo 1 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (! isset(self::LOGO_MIMES[$mime]) || @getimagesizefromstring($bytes) === false) {
            throw new \InvalidArgumentException('El logo debe ser una imagen PNG, JPG o WebP.');
        }

        return $mime;
    }

    /** "data:image/png;base64,...." -> raw bytes (null when it isn't one). */
    public static function decodeDataUrl(string $value): ?string
    {
        if (! preg_match('#^data:image/[a-z0-9.+-]+;base64,(.+)$#i', trim($value), $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);

        return $bytes === false ? null : $bytes;
    }

    /** Removes a logo stored by storeCompanyLogo (given its URL). */
    public static function deleteCompanyLogo(?string $url): void
    {
        if (! $url) {
            return;
        }
        $path = self::publicDiskName() === 'public'
            ? ltrim(Str::after($url, '/storage/'), '/')
            : ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        if (str_contains($path, '/' . self::BRANDING . '/')) {
            self::publicDisk()->delete($path);
        }
    }

    public static function avatarUrl(string $path): string
    {
        return self::publicDiskName() === 'public'
            ? '/storage/' . ltrim($path, '/')
            : self::publicDisk()->url($path);
    }
}
