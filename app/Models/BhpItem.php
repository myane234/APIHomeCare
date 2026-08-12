<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model BhpItem
 *
 * Merepresentasikan tabel master_bhp —
 * daftar Bahan Habis Pakai yang digunakan dalam layanan.
 *
 * @property int    $id_bhp
 * @property string $nama_bhp
 * @property string $tipe_bhp     — 'satuan' | 'paket'
 * @property float  $harga_modal
 * @property float  $harga_jual
 * @property bool   $is_active
 */
class BhpItem extends Model
{
    use HasFactory;

    protected $table      = 'master_bhp';
    protected $primaryKey = 'id_bhp';

    protected $fillable = [
        'nama_bhp',
        'tipe_bhp',
        'harga_modal',
        'harga_jual',
        'is_active',
    ];

    protected $casts = [
        'harga_modal' => 'decimal:2',
        'harga_jual'  => 'decimal:2',
        'is_active'   => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    /**
     * Layanan-layanan yang menggunakan BHP ini
     * melalui tabel pivot mapping_layanan_bhp.
     */
    public function layanans()
    {
        return $this->belongsToMany(
            MasterLayanan::class,
            'mapping_layanan_bhp',
            'id_bhp',
            'id_layanan'
        )->withPivot(['qty_default', 'is_mandatory'])->withTimestamps();
    }
}