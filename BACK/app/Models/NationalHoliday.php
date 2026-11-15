<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * National holidays catalog of a company, per country (managed by RRHH /
 * admin in Configuración > Festivos). "Importar nacionales" copies them into
 * the company holidays of a year.
 */
class NationalHoliday extends Model
{
    use BelongsToCompany;

    protected $fillable = ['country_code', 'date', 'name', 'type', 'recurring'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'recurring' => 'boolean',
    ];
}
