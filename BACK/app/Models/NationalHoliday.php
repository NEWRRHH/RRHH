<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform catalog of public holidays per country. Companies copy them into
 * their own holidays table; never tenant scoped.
 */
class NationalHoliday extends Model
{
    protected $fillable = ['country_code', 'date', 'name', 'type', 'recurring'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'recurring' => 'boolean',
    ];
}
