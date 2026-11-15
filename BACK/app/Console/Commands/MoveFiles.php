<?php

namespace App\Console\Commands;

use App\Support\FileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies every stored file to another disk and/or to the per-company layout
 * (companies/{id}/{documents|chat|avatars}/...), updating the paths in the DB.
 *
 *   php artisan files:move --dry-run            # see what would change
 *   php artisan files:move                      # reorganise on the current disks
 *   php artisan files:move --to=s3 --to-public=s3   # copy to S3 (then set FILES_DISK=s3 ...)
 *
 * Source files are kept unless --delete-source is given, so the move can be
 * repeated safely; files already in place are skipped.
 */
class MoveFiles extends Command
{
    protected $signature = 'files:move
        {--from= : private source disk (default: FILES_DISK)}
        {--to= : private target disk (default: FILES_DISK)}
        {--from-public= : avatars source disk (default: PUBLIC_FILES_DISK)}
        {--to-public= : avatars target disk (default: PUBLIC_FILES_DISK)}
        {--delete-source : remove the original after copying}
        {--dry-run : only list the changes}';

    protected $description = 'Move uploaded files to another disk and to the per-company folder layout';

    private int $moved = 0;
    private int $skipped = 0;
    private int $missing = 0;

    public function handle(): int
    {
        $from = $this->option('from') ?: FileStorage::diskName();
        $to = $this->option('to') ?: FileStorage::diskName();
        $fromPublic = $this->option('from-public') ?: FileStorage::publicDiskName();
        $toPublic = $this->option('to-public') ?: FileStorage::publicDiskName();

        $this->info("Privados: {$from} -> {$to} · Fotos: {$fromPublic} -> {$toPublic}" . ($this->option('dry-run') ? ' (simulación)' : ''));

        // documents: companies/{c}/documents/{user}/<file>
        foreach (DB::table('documents')->whereNotNull('filename')->orderBy('id')->cursor() as $row) {
            // payslips keep their employee folder: companies/{c}/nominas/{folder}/<file>
            $target = $row->category === 'payroll'
                ? FileStorage::folder($row->company_id, FileStorage::PAYROLL, basename(dirname((string) $row->filename))) . '/' . basename((string) $row->filename)
                : FileStorage::folder($row->company_id, FileStorage::DOCUMENTS, (string) $row->user_id) . '/' . basename((string) $row->filename);
            $this->moveOne($from, $to, (string) $row->filename, $target, fn ($p) => DB::table('documents')->where('id', $row->id)->update(['filename' => $p]));
        }

        // chat attachments: companies/{c}/chat/{uploader}/<file>
        foreach (DB::table('chat_attachments')->orderBy('id')->cursor() as $row) {
            $target = FileStorage::folder($row->company_id, FileStorage::CHAT, (string) $row->uploader_id) . '/' . basename((string) $row->path);
            $this->moveOne($from, $to, (string) $row->path, $target, fn ($p) => DB::table('chat_attachments')->where('id', $row->id)->update(['path' => $p]));
        }

        // profile photos stored as "/storage/<path>" (local public disk) or a full URL
        foreach (DB::table('users')->whereNotNull('photo')->where('photo', '<>', '')->orderBy('id')->cursor() as $row) {
            $source = $this->publicPathFromUrl((string) $row->photo, $fromPublic);
            if ($source === null) {
                $this->skipped++;
                continue;
            }
            $target = FileStorage::folder($row->company_id, FileStorage::AVATARS) . '/' . basename($source);
            $this->moveOne($fromPublic, $toPublic, $source, $target, function ($p) use ($row, $toPublic) {
                $url = $toPublic === 'public' ? '/storage/' . $p : Storage::disk($toPublic)->url($p);
                DB::table('users')->where('id', $row->id)->update(['photo' => $url]);
            }, public: true);
        }

        $this->info("Movidos: {$this->moved} · Ya en su lugar: {$this->skipped} · No encontrados: {$this->missing}");
        if ($this->missing) {
            $this->warn('Los archivos no encontrados figuran en la base pero no en el disco de origen (se dejaron como estaban).');
        }

        return self::SUCCESS;
    }

    private function moveOne(string $from, string $to, string $source, string $target, callable $save, bool $public = false): void
    {
        if ($from === $to && $source === $target) {
            $this->skipped++;
            return;
        }
        if (! Storage::disk($from)->exists($source)) {
            $this->missing++;
            $this->line("  <comment>no existe</comment> {$from}:{$source}");
            return;
        }

        $this->line("  {$from}:{$source} -> {$to}:{$target}");
        if ($this->option('dry-run')) {
            $this->moved++;
            return;
        }

        $stream = Storage::disk($from)->readStream($source);
        Storage::disk($to)->writeStream($target, $stream, $public ? ['visibility' => 'public'] : []);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $save($target);

        if ($this->option('delete-source') && ! ($from === $to && $source === $target)) {
            Storage::disk($from)->delete($source);
        }
        $this->moved++;
    }

    /** "/storage/avatars/1/x.png" -> "avatars/1/x.png" on the local public disk; URLs of other disks via their base URL. */
    private function publicPathFromUrl(string $photo, string $disk): ?string
    {
        if (str_starts_with($photo, '/storage/')) {
            return substr($photo, strlen('/storage/'));
        }
        $base = rtrim((string) config("filesystems.disks.{$disk}.url"), '/');
        if ($base !== '' && str_starts_with($photo, $base . '/')) {
            return substr($photo, strlen($base) + 1);
        }

        return null; // external URL we don't manage
    }
}
