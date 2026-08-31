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
        'id_kategori_layanan',
        'nama_layanan',
        'deskripsi_layanan',
        'harga',
        'foto_layanan',
        'tipe_layanan',
        'durasi_menit',
    ];

    protected $casts = [
        'harga'             => 'decimal:2',
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
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        return url(Storage::disk('public')->url($value));
    }

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    /**
     * Template tarif (MasterTarif) yang mereferensikan layanan ini.
     */
    public function masterTarif()
    {
        return $this->hasMany(MasterTarif::class, 'id_layanan', 'id_layanan');
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

}
