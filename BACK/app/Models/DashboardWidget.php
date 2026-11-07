<?php

namespace App\Models;

use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user dashboard widget layout row.
 */
class DashboardWidget extends Model
{
    use HasTableQuery;

    protected $table = 'user_dashboard_widgets';

    protected $guarded = ['id'];
}
