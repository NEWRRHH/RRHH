<?php

namespace App\Support;

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
 * - Every file lives under its company: companies/{company id}/{area}/...
 *   (documents, chat, avatars), so a company's files can be found, backed up
 *   or deleted together.
 */
final class FileStorage
{
    public const DOCUMENTS = 'documents';
    public const CHAT = 'chat';
    public const AVATARS = 'avatars';
    /** payslips dropped per employee: companies/{id}/nominas/{employee}/ (see PayrollSync) */
    public const PAYROLL = 'nominas';

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

    /** companies/{id}/{area}[/sub] ("platform" for users without company). */
    public static function folder(?int $companyId, string $area, string $sub = ''): string
    {
        $base = 'companies/' . ($companyId ?: 'platform') . '/' . $area;

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

    public static function avatarUrl(string $path): string
    {
        return self::publicDiskName() === 'public'
            ? '/storage/' . ltrim($path, '/')
            : self::publicDisk()->url($path);
    }
}
