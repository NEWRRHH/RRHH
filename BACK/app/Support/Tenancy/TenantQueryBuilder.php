<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Query\Builder;

/**
 * Base query builder of company-owned models.
 *
 * Fills company_id on every insert with the current tenant, so rows written
 * through Model::table()->insert(...) (which bypasses Eloquent events) are
 * still assigned to the right company.
 */
class TenantQueryBuilder extends Builder
{
    public function insert(array $values)
    {
        return parent::insert($this->withTenant($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return parent::insertGetId($this->withTenant($values), $sequence);
    }

    public function insertOrIgnore(array $values)
    {
        return parent::insertOrIgnore($this->withTenant($values));
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return parent::upsert($this->withTenant($values), $uniqueBy, $update);
    }

    /**
     * @param  array<mixed>  $values  one row (assoc array) or a list of rows
     * @return array<mixed>
     */
    private function withTenant(array $values): array
    {
        $companyId = Tenant::id();
        if ($companyId === null || $values === []) {
            return $values;
        }

        if (! is_array(reset($values))) {
            return $values + ['company_id' => $companyId];
        }

        return array_map(fn (array $row) => $row + ['company_id' => $companyId], $values);
    }
}
