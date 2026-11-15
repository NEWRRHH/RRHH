<?php

namespace App\Http\Controllers\Api;

use App\Events\DocumentsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchFile;
use App\Models\User;
use App\Services\PayrollSync;
use App\Services\PlanLimits;
use App\Support\Audit;
use App\Support\FileStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nóminas module (permission payroll.manage): the gestoría sends the month's
 * payslips named with each employee's DNI; RRHH uploads them all at once,
 * checks/fixes who gets each one and presses "Enviar". Each payslip is moved
 * to companies/{slug}/nominas/{employee}/ and shows up in that employee's
 * Documentos > Nóminas with the real-time badge.
 */
class PayrollController extends Controller
{
    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private function denied(Request $request)
    {
        return $request->user()->cannot('payroll.manage') ? response()->json(['message' => 'Forbidden'], 403) : null;
    }

    public static function periodLabel(string $period): string
    {
        [$y, $m] = array_map('intval', explode('-', $period));

        return ucfirst(self::MONTHS[max(1, min(12, $m)) - 1]) . ' ' . $y;
    }

    /** "30.111.222-A" -> "30111222A" */
    public static function normalizeDni(?string $dni): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $dni));
    }

    /**
     * Employee a file belongs to, from the DNI in its name ("30111222A.pdf",
     * "nomina_30111222A_2026-05.pdf", also without the letter).
     *
     * @param  Collection<int, User>  $users
     * @return array{0: ?User, 1: string} [user, dni|none|ambiguous]
     */
    public static function matchByDni(string $fileName, Collection $users): array
    {
        $tokens = array_filter(preg_split('/[^A-Za-z0-9]+/', strtoupper(pathinfo($fileName, PATHINFO_FILENAME))));
        $matches = $users->filter(function (User $u) use ($tokens) {
            $dni = self::normalizeDni($u->dni);
            if ($dni === '') {
                return false;
            }
            $digits = preg_replace('/\D/', '', $dni);
            foreach ($tokens as $token) {
                if ($token === $dni || ($digits !== '' && strlen($digits) >= 6 && $token === $digits)) {
                    return true;
                }
            }

            return false;
        });

        return match ($matches->count()) {
            1 => [$matches->first(), 'dni'],
            0 => [null, 'none'],
            default => [null, 'ambiguous'],
        };
    }

    private function employees(): Collection
    {
        return User::query()->whereNull('disabled_at')->orderBy('first_name')->get();
    }

    private function fileRow(PayrollBatchFile $f): array
    {
        $u = $f->user;

        return [
            'id' => $f->id,
            'original_name' => $f->original_name,
            'size' => (int) $f->size,
            'match' => $f->match,
            'sent_at' => $f->sent_at?->toIso8601String(),
            'user' => $u ? [
                'id' => $u->id,
                'name' => PayrollSync::fullName($u),
                'dni' => $u->dni,
                'photo' => $u->photo ?? $u->profile_photo_path ?? null,
            ] : null,
        ];
    }

    private function batchRow(PayrollBatch $b): array
    {
        $files = $b->files;

        return [
            'id' => $b->id,
            'period' => $b->period,
            'period_label' => self::periodLabel($b->period),
            'status' => $b->status,
            'sent_at' => $b->sent_at?->toIso8601String(),
            'created_at' => $b->created_at?->toIso8601String(),
            'files_count' => $files->count(),
            'matched_count' => $files->whereNotNull('user_id')->count(),
            'sent_count' => $files->whereNotNull('sent_at')->count(),
        ];
    }

    public function index(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $batches = PayrollBatch::with('files')->orderByDesc('period')->orderByDesc('id')->limit(50)->get();

        return response()->json(['batches' => $batches->map(fn ($b) => $this->batchRow($b))->values()]);
    }

    /** New draft for a month (or the existing draft of that month). */
    public function store(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']], [
            'period.regex' => 'Selecciona un mes válido.',
        ]);

        $batch = PayrollBatch::where('period', $data['period'])->where('status', PayrollBatch::DRAFT)->first()
            ?? PayrollBatch::create(['period' => $data['period'], 'status' => PayrollBatch::DRAFT, 'created_by' => $request->user()->id]);

        return response()->json(['batch' => $this->batchRow($batch->load('files'))]);
    }

    public function show(Request $request, int $id)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $batch = PayrollBatch::with('files.user')->findOrFail($id);
        $employees = $this->employees()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => PayrollSync::fullName($u),
            'dni' => $u->dni,
        ])->values();

        return response()->json([
            'batch' => $this->batchRow($batch),
            'files' => $batch->files->sortBy('original_name')->map(fn ($f) => $this->fileRow($f))->values(),
            'employees' => $employees,
            // active employees with no payslip in this batch yet
            'missing' => $employees->whereNotIn('id', $batch->files->pluck('user_id')->filter())->values(),
        ]);
    }

    /**
     * Add payslips to a draft (several per request; the browser sends the
     * folder in chunks). Each one is matched by the DNI in its name.
     */
    public function upload(Request $request, int $id, PlanLimits $limits)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $batch = PayrollBatch::findOrFail($id);
        if ($batch->status !== PayrollBatch::DRAFT) {
            return response()->json(['message' => 'Este lote ya fue enviado.'], 422);
        }
        $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'file|max:10240|mimes:pdf',
        ], [
            'files.*.mimes' => 'Solo se aceptan nóminas en PDF.',
            'files.*.max' => 'Cada nómina puede pesar como máximo 10 MB.',
        ]);

        $user = $request->user();
        $users = $this->employees();
        $folder = FileStorage::folder($user->company_id, 'nominas-lotes', (string) $batch->id);
        $added = [];
        foreach ($request->file('files') as $file) {
            if ($user->company && ($limitError = $limits->canStore($user->company, (int) $file->getSize()))) {
                return response()->json(['message' => $limitError, 'files' => $added], 422);
            }
            [$owner, $match] = self::matchByDni($file->getClientOriginalName(), $users);
            $path = $file->storeAs($folder, Str::random(24) . '.pdf', FileStorage::diskName());
            $row = PayrollBatchFile::create([
                'batch_id' => $batch->id,
                'user_id' => $owner?->id,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $path,
                'size' => (int) $file->getSize(),
                'match' => $match,
            ]);
            $added[] = $this->fileRow($row->load('user'));
        }

        return response()->json(['files' => $added]);
    }

    /** RRHH picks (or clears) the employee of a file by hand. */
    public function assign(Request $request, int $id, int $fileId)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $file = PayrollBatchFile::where('batch_id', $id)->findOrFail($fileId);
        if ($file->sent_at) {
            return response()->json(['message' => 'Esta nómina ya fue enviada.'], 422);
        }
        $data = $request->validate(['user_id' => 'nullable|integer']);
        $owner = $data['user_id'] ? User::whereNull('disabled_at')->find($data['user_id']) : null;
        if ($data['user_id'] && ! $owner) {
            return response()->json(['message' => 'Empleado no encontrado.'], 404);
        }
        $file->update(['user_id' => $owner?->id, 'match' => $owner ? 'manual' : 'none']);

        return response()->json(['file' => $this->fileRow($file->load('user'))]);
    }

    public function removeFile(Request $request, int $id, int $fileId)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $file = PayrollBatchFile::where('batch_id', $id)->findOrFail($fileId);
        if ($file->sent_at) {
            return response()->json(['message' => 'Esta nómina ya fue enviada.'], 422);
        }
        FileStorage::disk()->delete($file->path);
        $file->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * "Enviar": every matched payslip goes to its employee (folder + document
     * + real-time badge). Files without employee stay in the draft.
     */
    public function send(Request $request, int $id, PayrollSync $payroll)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $batch = PayrollBatch::with('files.user')->findOrFail($id);
        $pending = $batch->files->whereNull('sent_at')->whereNotNull('user_id');
        if ($pending->isEmpty()) {
            return response()->json(['message' => 'No hay nóminas asignadas para enviar.'], 422);
        }

        $disk = FileStorage::disk();
        $label = self::periodLabel($batch->period);
        $notify = [];
        $sent = 0;

        foreach ($pending as $file) {
            $owner = $file->user;
            if (! $owner || ! $disk->exists($file->path)) {
                continue;
            }
            $dir = $payroll->ensureEmployeeFolder($owner);
            $target = $dir . '/nomina-' . $batch->period . '.pdf';
            for ($i = 2; $disk->exists($target); $i++) {
                $target = $dir . '/nomina-' . $batch->period . '-' . $i . '.pdf';
            }

            DB::transaction(function () use ($disk, $file, $target, $owner, $label, $batch, &$sent) {
                $disk->move($file->path, $target);
                $documentId = Document::withoutGlobalScopes()->insertGetId([
                    'company_id' => $owner->company_id,
                    'description' => 'Nómina ' . $label,
                    'user_id' => $owner->id,
                    'filename' => $target,
                    'original_name' => 'Nómina ' . $label . '.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => (int) $file->size,
                    'visibility' => true,
                    'document_type_id' => null,
                    'category' => 'payroll',
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $file->update(['path' => $target, 'document_id' => $documentId, 'sent_at' => now()]);
                $sent++;
            });
            $notify[$owner->id] = true;
        }

        $allSent = $batch->files()->whereNull('sent_at')->doesntExist();
        if ($allSent) {
            $batch->update(['status' => PayrollBatch::SENT, 'sent_by' => $request->user()->id, 'sent_at' => now()]);
        }

        foreach (array_keys($notify) as $userId) {
            try {
                event(new DocumentsUpdated((int) $userId, DocumentController::unreadCount((int) $userId), 1));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        Audit::log('payroll.sent', $batch, ['period' => $batch->period, 'sent' => $sent]);

        return response()->json([
            'status' => 'ok',
            'sent' => $sent,
            'pending' => $batch->files()->whereNull('sent_at')->count(),
            'batch' => $this->batchRow($batch->fresh('files')),
        ]);
    }

    /** Discard a draft (its unsent files are deleted). */
    public function destroy(Request $request, int $id)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $batch = PayrollBatch::with('files')->findOrFail($id);
        if ($batch->files->whereNotNull('sent_at')->isNotEmpty()) {
            return response()->json(['message' => 'El lote tiene nóminas ya enviadas: no se puede borrar.'], 422);
        }
        foreach ($batch->files as $file) {
            FileStorage::disk()->delete($file->path);
        }
        $batch->delete();

        return response()->json(['status' => 'ok']);
    }
}
