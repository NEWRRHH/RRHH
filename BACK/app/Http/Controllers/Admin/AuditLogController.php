<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Audit trail browser (every company + platform actions).
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => 'nullable|string|max:80',
            'q' => 'nullable|string|max:200',
            'company_id' => 'nullable|integer',
            'include_logins' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $logs = AuditLog::query()
            ->with('company:id,name')
            ->when(! ($data['include_logins'] ?? false), fn ($q) => $q->where('action', '!=', 'auth.login'))
            ->when($data['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', $action . '%'))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('actor_name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
