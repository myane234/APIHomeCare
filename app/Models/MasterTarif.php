<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model MasterTarif
 *
 * Blueprint utama semua komponen tarif yang dihitung secara dinamis.
 */
class MasterTarif extends Model
{
    use HasFactory;

    protected $table = 'master_tarif';
    protected $primaryKey = 'id_master_tarif';

    protected $fillable = [
        'nama_template',
        'id_layanan',
        'id_kota',
        'tarif_pasien',
        'transport_base_fare',
        'transport_per_km',
        'total_bhp',
        'potongan_persen_nakes',
        'fee_nakes_nominal',
        'total_ppn',
        'total_biaya_admin',
        'total_asuransi',
        'subtotal',
        'total_tarif_final',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'tarif_pasien' => 'decimal:2',
        'transport_base_fare' => 'decimal:2',
        'transport_per_km' => 'decimal:2',
        'total_bhp' => 'decimal:2',
        'fee_nakes_nominal' => 'decimal:2',
        'total_ppn' => 'decimal:2',
        'total_biaya_admin' => 'decimal:2',
        'total_asuransi' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_tarif_final' => 'decimal:2',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }

    public function kota()
    {
        return $this->belongsTo(KotaKabupaten::class, 'id_kota', 'id_kota');
    }
}