<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;

/**
 * Company holiday (festivo).
 */
class Holiday extends Model
{
    use BelongsToCompany;
    use HasTableQuery;

    // is_working_day: the company works that holiday (normal working day, still labelled)
    protected $fillable = ['date', 'name', 'type', 'recurring', 'is_working_day'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'recurring' => 'boolean',
        'is_working_day' => 'boolean',
    ];
}
