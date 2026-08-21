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
        'id_provinsi',
        'tarif_pasien',
        'fee_nakes_tipe',
        'fee_nakes_nilai',
        'transport_base_fare',
        'transport_per_km',
        'fee_nakes_nominal',
        'fee_platform_nominal',
        'persen_ppn',
        'total_ppn',
        'total_biaya_admin',
        'total_biaya_lainnya',
        'subtotal',
        'total_tarif_final',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'tarif_pasien' => 'decimal:2',
        'fee_nakes_nilai' => 'decimal:2',
        'transport_base_fare' => 'decimal:2',
        'transport_per_km' => 'decimal:2',
        'fee_nakes_nominal' => 'decimal:2',
        'fee_platform_nominal' => 'decimal:2',
        'persen_ppn' => 'decimal:2',
        'total_ppn' => 'decimal:2',
        'total_biaya_admin' => 'decimal:2',
        'total_biaya_lainnya' => 'decimal:2',
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

    public function provinsi()
    {
        return $this->belongsTo(WilayahLayanan::class, 'id_provinsi', 'id_provinsi');
    }

    public function layananTermasuk()
    {
        return $this->belongsToMany(
            MasterLayanan::class,
            'master_tarif_layanan',
            'id_master_tarif',
            'id_layanan',
            'id_master_tarif',
            'id_layanan'
        );
    }

    public function komponenTarif()
    {
        return $this->belongsToMany(
            MasterKomponenBiaya::class,
            'master_tarif_komponen',
            'id_master_tarif',
            'id_komponen',
            'id_master_tarif',
            'id_komponen'
        );
    }
}