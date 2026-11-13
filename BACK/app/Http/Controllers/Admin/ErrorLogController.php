<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Application errors captured by ErrorLogRecorder.
 */
class ErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => 'nullable|in:open,resolved,all',
            'q' => 'nullable|string|max:200',
            'company_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);
        $status = $data['status'] ?? 'open';

        $errors = ErrorLog::query()
            ->with('company:id,name')
            ->when($status === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('message', 'like', "%{$term}%")
                ->orWhere('exception', 'like', "%{$term}%")
                ->orWhere('url', 'like', "%{$term}%")))
            ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->orderByDesc('last_seen_at')
            ->paginate($data['per_page'] ?? 25, ['id', 'company_id', 'user_id', 'exception', 'message', 'file', 'line', 'method', 'url', 'occurrences', 'last_seen_at', 'resolved_at', 'created_at']);

        return response()->json($errors);
    }

    public function show(int $id)
    {
        return response()->json(ErrorLog::with(['company:id,name', 'user:id,name,email'])->findOrFail($id));
    }

    public function resolve(int $id)
    {
        $error = ErrorLog::findOrFail($id);
        $error->update(['resolved_at' => now()]);
        Audit::log('error.resolved', $error, ['exception' => $error->exception], companyId: null);

        return response()->json(['status' => 'ok', 'error' => $error]);
    }

    public function reopen(int $id)
    {
        $error = ErrorLog::findOrFail($id);
        $error->update(['resolved_at' => null]);

        return response()->json(['status' => 'ok', 'error' => $error]);
    }
}
