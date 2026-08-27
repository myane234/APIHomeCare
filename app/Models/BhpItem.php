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
 * @property float  $harga_modal
 * @property float  $harga_jual
 * @property string $tipe_margin  — 'persen' | 'nominal'
 * @property float  $nilai_margin
 * @property bool   $is_active
 */
class BhpItem extends Model
{
    use HasFactory;

    protected $table      = 'master_bhp';
    protected $primaryKey = 'id_bhp';

    protected $fillable = [
        'nama_bhp',
        'harga_modal',
        'harga_jual',
        'tipe_margin',
        'nilai_margin',
        'is_active',
    ];

    protected $casts = [
        'harga_modal'  => 'decimal:2',
        'harga_jual'   => 'decimal:2',
        'nilai_margin' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    /**
     * Hitung otomatis harga_jual saat data dibuat / diubah
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if ($item->tipe_margin === 'persen') {
                $item->harga_jual = $item->harga_modal + ($item->harga_modal * ($item->nilai_margin / 100));
            } else {
                $item->harga_jual = $item->harga_modal + $item->nilai_margin;
            }
        });
    }

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