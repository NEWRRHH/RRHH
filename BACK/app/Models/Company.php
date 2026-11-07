<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant. Every company-owned row has company_id pointing here.
 * Users belong to exactly one company (super admins to none).
 */
class Company extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = ['name', 'slug', 'status', 'plan', 'country_code'];

    /** companies.plan stores the plan code */
    public function planModel(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan', 'code');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
