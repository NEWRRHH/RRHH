<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $category = (string) $request->query('category', '');
        $query = DB::table('documents')
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
        ]);

        return response()->json(['documents' => $rows])->header('Access-Control-Allow-Origin', '*');
    }

    public function upload(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'file' => 'required|file|max:10240',
            'category' => 'required|string|in:medical,receipt,payroll',
            'description' => 'nullable|string|max:400',
        ]);

        $file = $request->file('file');
        $safeName = Str::random(24) . '.' . $file->getClientOriginalExtension();
        $storedPath = $file->storeAs("documents/{$user->id}", $safeName, 'local');

        $id = DB::table('documents')->insertGetId([
            'description' => $data['description'] ?? '',
            'user_id' => $user->id,
            'filename' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'visibility' => true,
            'document_type_id' => null,
            'category' => $data['category'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'document_id' => $id])->header('Access-Control-Allow-Origin', '*');
    }

    public function download(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $doc = DB::table('documents')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first(['id', 'filename', 'original_name']);

        if (!$doc || empty($doc->filename)) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        if (!Storage::disk('local')->exists($doc->filename)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        $downloadName = $doc->original_name ?: basename((string) $doc->filename);
        return Storage::disk('local')->download($doc->filename, $downloadName);
    }
}
