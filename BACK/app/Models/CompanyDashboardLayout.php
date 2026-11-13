<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Default dashboard of a company (one row per company).
 */
class CompanyDashboardLayout extends Model
{
    use BelongsToCompany;

    protected $fillable = ['slots', 'settings', 'updated_by'];

    protected $casts = [
        'slots' => 'array',
        'settings' => 'array',
    ];
}
