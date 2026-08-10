<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKomponenBiaya extends Model
{
    use HasFactory;

    protected $table = 'master_komponen_biaya';
    protected $primaryKey = 'id_komponen';

    protected $fillable = [
        'nama_komponen',
        'tipe_komponen',
        'jenis_nilai',
        'nilai',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'nilai' => 'decimal:2',
    ];
}
