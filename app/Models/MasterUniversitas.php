<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterUniversitas extends Model
{
    use HasFactory;

    protected $table = 'master_universitas';
    protected $primaryKey = 'id_universitas';

    protected $fillable = [
        'nama_universitas',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
