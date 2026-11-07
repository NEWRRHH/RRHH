<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription plan with its limits (null limit = unlimited).
 * Platform-level, managed by the super admin.
 */
class Plan extends Model
{
    protected $fillable = ['code', 'name', 'max_employees', 'max_storage_mb', 'is_default'];

    protected $casts = [
        'max_employees' => 'integer',
        'max_storage_mb' => 'integer',
        'is_default' => 'boolean',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan', 'code');
    }

    public static function defaultCode(): string
    {
        return (string) (static::where('is_default', true)->value('code') ?? 'free');
    }
}
