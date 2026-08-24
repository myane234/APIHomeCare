<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model MasterTarif
 */
class MasterTarif extends Model
{
    use HasFactory;

    protected $table = 'master_tarif';
    protected $primaryKey = 'id_master_tarif';

    protected $fillable = [
        'nama_template',
        'id_kategori_tarif',
        'id_layanan',
        'id_kota',
        'id_provinsi',
        'fee_nakes_tipe',
        'fee_nakes_nilai',
        'fee_nakes_nominal',
        'fee_platform_nominal',
        'is_active',
    ];

    protected $casts = [
        'fee_nakes_nilai' => 'decimal:2',
        'fee_nakes_nominal' => 'decimal:2',
        'fee_platform_nominal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }

    public function kategoriTarif()
    {
        return $this->belongsTo(MasterKategoriTarif::class, 'id_kategori_tarif', 'id_kategori_tarif');
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