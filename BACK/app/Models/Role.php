<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A permission profile (e.g. "Admin", "RRHH", "IT"). Each user has exactly
 * one role (users.role_id). The role flagged is_admin bypasses every check.
 */
class Role extends Model
{
    use BelongsToCompany;
    use HasTableQuery;

    protected $fillable = ['name', 'description'];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
