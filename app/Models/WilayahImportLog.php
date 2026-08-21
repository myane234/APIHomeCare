<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WilayahImportLog extends Model
{
    protected $fillable = [
        'run_id',
        'level',
        'message',
    ];
}
