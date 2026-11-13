<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\Tenant;
use App\Support\Tenancy\TenantQueryBuilder;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a company (tenant):
 *
 * - every query is filtered by the current company (TenantScope);
 * - company_id is filled automatically on create / insert;
 * - Model::table() (HasTableQuery) inherits both behaviours.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->company_id) && Tenant::id() !== null) {
                $model->company_id = Tenant::id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Base builder that injects company_id on raw inserts.
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new TenantQueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor(),
        );
    }
}
