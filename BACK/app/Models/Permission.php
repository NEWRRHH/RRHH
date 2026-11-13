<?php

namespace App\Models;

use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Global permission catalog (e.g. "employees.edit"). Shared by every company;
 * each company decides which of them its roles get.
 */
class Permission extends Model
{
    use HasTableQuery;

    protected $fillable = ['code', 'name', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')->withTimestamps();
    }
}
