<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventType extends Model
{
    use BelongsToCompany;
    use HasTableQuery;
    use SoftDeletes;

    /** Stable codes the application logic relies on (ids differ per company). */
    public const VACATION = 'vacation';
    public const ATTENDANCE_REQUEST = 'attendance_request';
    public const GENERAL = 'general';
    public const PERMISSION = 'permission';
    public const MEETING = 'meeting';
    /** Remote work day (approved = "de teletrabajo" in who's in / avatars) */
    public const REMOTE_WORK = 'remote_work';

    /**
     * Fixed colour of each type (not chosen by the employee). Same palette as
     * the icons in the frontend (utils/eventTypeIcons.ts).
     */
    public const COLORS = [
        self::VACATION => '#14B8A6',           // teal
        self::PERMISSION => '#F59E0B',         // amber
        self::GENERAL => '#3B82F6',            // blue
        self::MEETING => '#8B5CF6',            // violet
        self::ATTENDANCE_REQUEST => '#F97316', // orange
        self::REMOTE_WORK => '#0EA5E9',        // sky
    ];

    public const DEFAULT_COLOR = '#64748B'; // slate: custom types

    public static function colorFor(?string $code): string
    {
        return self::COLORS[$code ?? ''] ?? self::DEFAULT_COLOR;
    }

    /** Types whose requests need a description (the rest: optional). */
    public const DESCRIPTION_REQUIRED = [self::GENERAL, self::PERMISSION];

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
