<?php

namespace App\Models;

use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;

/**
 * Company holiday (festivo).
 */
class Holiday extends Model
{
    use HasTableQuery;

    protected $fillable = ['date', 'name', 'type', 'recurring'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'recurring' => 'boolean',
    ];
}
