<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules limited to the current company.
 *
 * Laravel's "exists"/"unique" rules query the table directly (no model
 * scopes), so without this an id from another company would validate.
 */
final class TenantRule
{
    /**
     * The value must exist in $table AND belong to the current company.
     */
    public static function exists(string $table, string $column = 'id', bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, $column);
        if (Tenant::id() !== null) {
            $rule->where('company_id', Tenant::id());
        }
        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        return $rule;
    }

    /**
     * The value must be unique in $table inside the current company.
     */
    public static function unique(string $table, string $column, bool $softDeletes = false): Unique
    {
        $rule = Rule::unique($table, $column);
        if (Tenant::id() !== null) {
            $rule->where('company_id', Tenant::id());
        }
        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        return $rule;
    }
}
