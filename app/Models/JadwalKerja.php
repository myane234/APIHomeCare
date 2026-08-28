<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalKerja extends Model
{
    protected $table = 'jadwal_kerjas';
    protected $primaryKey = 'id_jadwal';

    protected $fillable = [
        'id_tenaga_medis',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    public function tenagaMedis()
    {
        return $this->belongsTo(TenagaMedis::class, 'id_tenaga_medis', 'id_tenaga_medis');
    }
}
