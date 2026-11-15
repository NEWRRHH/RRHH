<?php

namespace App\Http\Controllers\Api;

use App\Events\DocumentsUpdated;
use App\Services\PayrollSync;
use App\Support\FileStorage;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    private function allowedCategories(): array
    {
        return ['medical', 'receipt', 'payroll'];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        if (!$user->hasPermission('documents.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $category = (string) $request->query('category', '');
        // payslips dropped in companies/{id}/nominas/{employee}/ show up here
        if ($category === '' || $category === 'payroll') {
            app(PayrollSync::class)->syncUser($user);
        }
        $query = Document::table()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        if ($category !== '' && in_array($category, $this->allowedCategories(), true)) {
            $query->where('category', $category);
        }

        $rows = $query->get([
            'id',
            'description',
            'filename',
            'original_name',
            'mime_type',
            'size',
            'category',
            'created_at',
            'read_at', // null = new (highlighted until the tab is opened)
        ]);

        return response()->json(['documents' => $rows]);
    }

    public function upload(Request $request, PlanLimits $limits)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        if (!$user->hasPermission('documents.upload')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpeg,jpg,png,gif,doc,docx,xls,xlsx',
            'category' => 'required|string|in:medical,receipt,payroll',
            'description' => 'nullable|string|max:400',
        ]);

        $file = $request->file('file');

        // plan limit (storage)
        if ($user->company && ($limitError = $limits->canStore($user->company, (int) $file->getSize()))) {
            return response()->json(['message' => $limitError], 422);
        }
        // companies/{company}/documents/{user}/<random> on FILES_DISK
        $storedPath = FileStorage::storePrivate($file, $user->company_id, FileStorage::DOCUMENTS, (string) $user->id);

        $id = Document::table()->insertGetId([
            'description' => $data['description'] ?? '',
            'user_id' => $user->id,
            'filename' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'visibility' => true,
            'document_type_id' => null,
            'category' => $data['category'],
            'read_at' => now(), // own upload: nothing new to warn about
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'document_id' => $id]);
    }

    /** Documents the user hasn't seen yet (badge of the Documentos menu / card). */
    public static function unreadCount(int $userId): int
    {
        return Document::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNull('read_at')
            ->count();
    }

    public function unread(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('documents.view')) {
            return response()->json(['unread' => 0, 'by_category' => []]);
        }
        // a payslip may have just been dropped in the folder
        app(PayrollSync::class)->syncUser($user);

        $byCategory = Document::table()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereNull('read_at')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return response()->json(['unread' => (int) $byCategory->sum(), 'by_category' => $byCategory]);
    }

    /**
     * The user opened a tab of Documentos: its documents count as seen.
     * Other tabs/devices drop the badge in real time.
     */
    public function markRead(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['category' => 'nullable|string|in:' . implode(',', $this->allowedCategories())]);

        $updated = Document::table()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->update(['read_at' => now()]);

        $unread = self::unreadCount($user->id);
        if ($updated) {
            event(new DocumentsUpdated($user->id, $unread));
        }

        return response()->json(['status' => 'ok', 'unread' => $unread]);
    }

    public function download(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        if (!$user->hasPermission('documents.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $doc = Document::table()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first(['id', 'filename', 'original_name']);

        if (!$doc || empty($doc->filename)) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        if (!FileStorage::disk()->exists($doc->filename)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        $downloadName = $doc->original_name ?: basename((string) $doc->filename);
        return FileStorage::disk()->download($doc->filename, $downloadName);
    }
}
