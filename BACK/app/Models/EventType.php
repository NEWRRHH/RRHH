<?php

namespace App\Models;

use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventType extends Model
{
    use HasTableQuery;
    use SoftDeletes;

    /** Stable codes the application logic relies on (ids differ per company). */
    public const VACATION = 'vacation';
    public const ATTENDANCE_REQUEST = 'attendance_request';

    protected $fillable = ['name', 'code'];

    /**
     * Id of the event type with the given code (null when missing).
     */
    public static function idFor(string $code): ?int
    {
        $id = static::table()->where('code', $code)->value('id');

        return $id ? (int) $id : null;
    }
}
