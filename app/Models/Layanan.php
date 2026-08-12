<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layanan extends Model
{
    use HasFactory;

    protected $table = 'master_layanan';
    protected $primaryKey = 'id_layanan';

    protected $fillable = [
        'id_kategori_layanan',
        'nama_layanan',
        'deskripsi_layanan',
        'foto_layanan',
        'tipe_layanan',
        'durasi_menit',
    ];

    public function kategori()
    {
        return $this->belongsTo(KategoriLayanan::class, 'id_kategori_layanan', 'id_kategori_layanan');
    }
}
