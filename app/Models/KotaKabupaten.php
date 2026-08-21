<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KotaKabupaten extends Model
{
    protected $table = 'master_kota_kabupaten';
    protected $primaryKey = 'id_kota';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kota', 
        'id_provinsi',
        'nama_kota',
    ];

    /**
     * Relasi One-to-Many ke Kecamatan
     */
    public function kecamatans(): HasMany
    {
        return $this->hasMany(Kecamatan::class, 'regency_id', 'id_kota');
    }

    /**
     * Relasi Many-to-One ke Provinsi / WilayahLayanan
     */
    public function provinsi(): BelongsTo
    {
        // Pastikan nama model tujuan sesuai (WilayahLayanan::class atau Provinsi::class)
        return $this->belongsTo(WilayahLayanan::class, 'id_provinsi', 'id_provinsi');
    }
}