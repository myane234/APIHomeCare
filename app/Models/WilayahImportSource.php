<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WilayahImportSource extends Model
{
    protected $fillable = [
        'source_type',
        'base_url',
        'provinces_url',
        'regencies_url',
        'districts_url',
        'villages_url',
        'file_path',
        'file_name',
    ];
}