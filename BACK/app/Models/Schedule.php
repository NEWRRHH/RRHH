<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Work-shift template (start/end time + week days).
 */
class Schedule extends Model
{
    use BelongsToCompany;
    use HasTableQuery;
    use SoftDeletes;

    protected $fillable = ['start_time', 'end_time', 'days'];
}
