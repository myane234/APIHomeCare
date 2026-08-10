<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKategoriPembayaran extends Model
{
    use HasFactory;

    protected $table = 'master_kategori_pembayaran';
    protected $primaryKey = 'id_kategori_pembayaran';

    protected $fillable = [
        'nama_kategori',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function metodePembayaran()
    {
        return $this->hasMany(MasterMetodePembayaran::class, 'id_kategori_pembayaran', 'id_kategori_pembayaran');
    }
}
