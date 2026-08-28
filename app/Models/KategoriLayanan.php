<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriLayanan extends Model
{
    use HasFactory;

    protected $table = 'kategori_layanans';
    protected $primaryKey = 'id_kategori_layanan';

    protected $fillable = [
        'nama_kategori',
        'photo_kategori', // Tambahkan kolom ini
    ];
}