<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Announcement (comunicado).
 */
class Post extends Model
{
    use BelongsToCompany;
    use HasTableQuery;

    /** posts only has created_at */
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PostRead::class);
    }
}
