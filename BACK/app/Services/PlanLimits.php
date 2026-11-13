<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Document;
use App\Models\Plan;
use App\Models\User;

/**
 * Enforces the limits of a company's plan. Each check returns null when the
 * action is allowed, or the message to show the user.
 */
class PlanLimits
{
    public function plan(Company $company): ?Plan
    {
        return Plan::where('code', $company->plan ?: Plan::defaultCode())->first();
    }

    /**
     * @return array{plan: ?array, employees: array{used: int, max: ?int}, storage_mb: array{used: float, max: ?int}}
     */
    public function usage(Company $company): array
    {
        $plan = $this->plan($company);

        return [
            'plan' => $plan?->only(['code', 'name', 'max_employees', 'max_storage_mb']),
            'employees' => ['used' => $this->employeeCount($company), 'max' => $plan?->max_employees],
            'storage_mb' => ['used' => round($this->storageBytes($company) / 1048576, 1), 'max' => $plan?->max_storage_mb],
        ];
    }

    public function canAddEmployee(Company $company): ?string
    {
        $max = $this->plan($company)?->max_employees;
        if ($max !== null && $this->employeeCount($company) >= $max) {
            return "Has alcanzado el límite de {$max} empleados de tu plan. Contacta con el soporte para ampliarlo.";
        }

        return null;
    }

    public function canStore(Company $company, int $bytes): ?string
    {
        $maxMb = $this->plan($company)?->max_storage_mb;
        if ($maxMb !== null && $this->storageBytes($company) + $bytes > $maxMb * 1048576) {
            return "No hay espacio suficiente: tu plan incluye {$maxMb} MB de almacenamiento.";
        }

        return null;
    }

    private function employeeCount(Company $company): int
    {
        return User::withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->count();
    }

    private function storageBytes(Company $company): int
    {
        return (int) Document::withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->sum('size');
    }
}
