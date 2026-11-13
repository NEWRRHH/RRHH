<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query of a company-owned model to the current tenant.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = Tenant::id();
        if ($companyId !== null) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        }
    }
}
