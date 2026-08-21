<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WilayahImportSource extends Model
{
    protected $fillable = [
        'source_type',
        'base_url',
        'file_path',
        'file_name',
    ];
}