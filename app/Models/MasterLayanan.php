<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Model MasterLayanan
 *
 * Merepresentasikan tabel master_layanan.
 * Setiap layanan mereferensikan satu template tarif (MasterTarif)
 * yang menentukan komponen biaya (biaya admin, PPN, fee nakes, transport).
 *
 * @property int         $id_layanan
 * @property int|null    $id_master_tarif
 * @property int         $id_kategori_layanan
 * @property string      $nama_layanan
 * @property string|null $deskripsi_layanan
 * @property float       $harga             — tarif jasa / SL
 * @property bool        $include_transport — true = transport sudah termasuk harga
 * @property string      $foto_layanan
 * @property string      $tipe_layanan      — 'durasi' | 'tindakan'
 * @property int|null    $durasi_menit
 */
class MasterLayanan extends Model
{
    use HasFactory;

    protected $table      = 'master_layanan';
    protected $primaryKey = 'id_layanan';

    protected $fillable = [
        'id_master_tarif',
        'id_kategori_layanan',
        'nama_layanan',
        'deskripsi_layanan',
        'harga',
        'include_transport',
        'foto_layanan',
        'tipe_layanan',
        'durasi_menit',
    ];

    protected $casts = [
        'harga'             => 'decimal:2',
        'include_transport' => 'boolean',
        'durasi_menit'      => 'integer',
    ];

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    /**
     * Kembalikan URL penuh foto layanan.
     */
    public function getFotoLayananAttribute($value): ?string
    {
        if (!$value) return null;
        // Jika sudah berupa URL penuh, kembalikan apa adanya
        if (str_starts_with($value, 'http')) return $value;
        return Storage::disk('public')->url($value);
    }

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    /**
     * Template tarif / blueprint komponen biaya untuk layanan ini.
     */
    public function masterTarif()
    {
        return $this->belongsTo(MasterTarif::class, 'id_master_tarif', 'id_master_tarif');
    }

    /**
     * Kategori layanan.
     */
    public function kategori()
    {
        return $this->belongsTo(KategoriLayanan::class, 'id_kategori_layanan', 'id_kategori_layanan');
    }

    /**
     * BHP (Bahan Habis Pakai) yang terkait dengan layanan ini
     * melalui tabel pivot mapping_layanan_bhp.
     */
    public function bhpItems()
    {
        return $this->belongsToMany(
            BhpItem::class,
            'mapping_layanan_bhp',
            'id_layanan',
            'id_bhp'
        )->withPivot(['qty_default', 'is_mandatory'])->withTimestamps();
    }

    /**
     * Tarif spesifik per kota (dari tabel master_tarif_layanan).
     */
    public function tarifLayanan()
    {
        return $this->hasMany(MasterTarifLayanan::class, 'id_layanan', 'id_layanan');
    }
}
