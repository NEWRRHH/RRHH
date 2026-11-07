<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Audit trail: Audit::log('role.deleted', $role, ['name' => 'IT']).
 *
 * The actor is the authenticated user; the company defaults to the current
 * tenant, then to the target's company. Writing the audit entry never breaks
 * the action being audited.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function log(
        string $action,
        ?Model $target = null,
        array $metadata = [],
        ?string $description = null,
        ?int $companyId = null,
        ?User $actor = null,
    ): void {
        try {
            $actor ??= request()->user();
            $companyId ??= Tenant::id()
                ?? ($target instanceof \App\Models\Company ? $target->id : null)
                ?? ($target?->getAttribute('company_id'))
                ?? $actor?->company_id;

            AuditLog::create([
                'actor_id' => $actor?->id,
                'actor_name' => $actor ? trim(($actor->name ?: '') . ' <' . $actor->email . '>') : 'sistema',
                'company_id' => $companyId,
                'action' => $action,
                'target_type' => $target ? class_basename($target) : null,
                'target_id' => $target?->getKey(),
                'description' => $description,
                'metadata' => $metadata ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('audit log failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
