<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model MasterTarifLayanan
 *
 * Merepresentasikan tabel master_tarif_layanan —
 * tarif spesifik per kota/nasional untuk sebuah layanan.
 *
 * @property int        $id_tarif
 * @property int        $id_layanan
 * @property int|null   $id_kota     — null = berlaku nasional
 * @property float      $tarif_pasien
 * @property int        $potongan_persen
 * @property bool       $is_active
 */
class MasterTarifLayanan extends Model
{
    use HasFactory;

    protected $table      = 'master_tarif_layanan';
    protected $primaryKey = 'id_tarif';

    protected $fillable = [
        'id_layanan',
        'id_kota',
        'tarif_pasien',
        'potongan_persen',
        'is_active',
    ];

    protected $casts = [
        'tarif_pasien'   => 'decimal:2',
        'potongan_persen'=> 'integer',
        'is_active'      => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }

    public function kota()
    {
        return $this->belongsTo(KotaKabupaten::class, 'id_kota', 'id_kota');
    }
}
