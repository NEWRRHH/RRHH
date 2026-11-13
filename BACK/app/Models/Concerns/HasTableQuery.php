<?php

namespace App\Models\Concerns;

use Illuminate\Database\Query\Builder;

/**
 * Drop-in replacement for DB::table('<model table>').
 *
 * Returns a plain query builder (rows come back as stdClass, exactly like
 * DB::table) but built from the Eloquent model, so every global scope
 * registered on the model (soft deletes today, the company/tenant scope in
 * the multi-tenant phase) is applied automatically.
 */
trait HasTableQuery
{
    public static function table(): Builder
    {
        return static::query()->toBase();
    }
}
