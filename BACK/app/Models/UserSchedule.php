<?php

namespace App\Models;

use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: schedule templates assigned to a user.
 */
class UserSchedule extends Model
{
    use HasTableQuery;

    protected $table = 'user_schedules';

    protected $fillable = ['user_id', 'schedule_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
