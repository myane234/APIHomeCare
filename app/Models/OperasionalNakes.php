<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperasionalNakes extends Model
{
    protected $table = 'operasional_nakes';
    protected $primaryKey = 'id_operasional_nakes';

    protected $fillable = [
        'id_tenaga_medis',
        'id_wilayah_layanan',
        'kategori_layanan',
        'waktu_layanan',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'kategori_layanan' => 'array',
        'waktu_layanan' => 'array',
    ];

    public function tenagaMedis()
    {
        return $this->belongsTo(TenagaMedis::class, 'id_tenaga_medis', 'id_tenaga_medis');
    }

    public function wilayahLayanan()
    {
        return $this->belongsTo(WilayahLayanan::class, 'id_wilayah_layanan', 'id_provinsi');
    }
}
