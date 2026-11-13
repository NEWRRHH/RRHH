<?php

namespace App\Models;

use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Model;

class PostRead extends Model
{
    use HasTableQuery;

    protected $guarded = ['id'];
}
