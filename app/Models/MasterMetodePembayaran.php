<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterMetodePembayaran extends Model
{
    use HasFactory;

    protected $table = 'master_metode_pembayaran';
    protected $primaryKey = 'id_metode';

    protected $fillable = [
        'id_kategori_pembayaran',
        'payment_type',
        'nama_metode',
        'tipe_potongan',
        'nilai_potongan',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'nilai_potongan' => 'decimal:2',
    ];

    public function kategori()
    {
        return $this->belongsTo(MasterKategoriPembayaran::class, 'id_kategori_pembayaran', 'id_kategori_pembayaran');
    }
}
