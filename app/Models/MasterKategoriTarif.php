<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKategoriTarif extends Model
{
    use HasFactory;

    protected $table = 'master_kategori_tarif';
    protected $primaryKey = 'id_kategori_tarif';

    protected $fillable = [
        'nama_kategori',
        'biaya_tambahan',
        'is_default',
    ];

    protected $casts = [
        'biaya_tambahan' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function masterTarifs()
    {
        return $this->hasMany(MasterTarif::class, 'id_kategori_tarif', 'id_kategori_tarif');
    }
}
