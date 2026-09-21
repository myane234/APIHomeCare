<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKategoriTarif extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'master_kategori_tarif';
    protected $primaryKey = 'id_kategori_tarif';

    protected $fillable = [
        'nama_kategori',
        'biaya_tambahan',
        'is_default',
        'hari_berlaku',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'biaya_tambahan' => 'decimal:2',
        'is_default' => 'boolean',
        'hari_berlaku' => 'array',
    ];

    public function masterTarifs()
    {
        return $this->hasMany(MasterTarif::class, 'id_kategori_tarif', 'id_kategori_tarif');
    }
}
