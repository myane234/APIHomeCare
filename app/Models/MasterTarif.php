<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model MasterTarif
 *
 * Merepresentasikan tabel master_tarif yang berfungsi sebagai
 * template / blueprint komponen biaya layanan. Nilai-nilainya
 * akan di-snapshot ke tabel transaksis saat booking dibuat
 * sehingga histori tarif tetap akurat meskipun template diubah.
 *
 * @property int         $id_master_tarif
 * @property string      $nama_template
 * @property string|null $keterangan
 * @property float       $biaya_admin
 * @property float       $persentase_ppn
 * @property float       $fee_nakes_persen
 * @property float       $fee_nakes_nominal
 * @property float       $tarif_transport_per_km
 * @property bool        $is_active
 */
class MasterTarif extends Model
{
    use HasFactory;

    protected $table      = 'master_tarif';
    protected $primaryKey = 'id_master_tarif';

    protected $fillable = [
        'nama_template',
        'keterangan',
        'biaya_admin',
        'persentase_ppn',
        'fee_nakes_persen',
        'fee_nakes_nominal',
        'tarif_transport_per_km',
        'is_active',
    ];

    protected $casts = [
        'biaya_admin'            => 'decimal:2',
        'persentase_ppn'         => 'decimal:2',
        'fee_nakes_persen'       => 'decimal:2',
        'fee_nakes_nominal'      => 'decimal:2',
        'tarif_transport_per_km' => 'decimal:2',
        'is_active'              => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    /**
     * Layanan-layanan yang menggunakan template tarif ini.
     */
    public function layanans()
    {
        return $this->hasMany(MasterLayanan::class, 'id_master_tarif', 'id_master_tarif');
    }
}