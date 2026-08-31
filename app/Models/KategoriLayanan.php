<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;

class KategoriLayanan extends Model
{
    use HasFactory;

    protected $table = 'kategori_layanans';
    protected $primaryKey = 'id_kategori_layanan';

    protected $fillable = [
        'nama_kategori',
        'photo_kategori', 
    ];

    protected $appends = ['photo_kategori_url'];


    protected function photoKategoriUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->photo_kategori) {
                    return asset('storage/kategori/default.jpg');
                }

                if (filter_var($this->photo_kategori, FILTER_VALIDATE_URL)) {
                    return $this->photo_kategori;
                }

                return Storage::disk('public')->url($this->photo_kategori);
            }
        );
    }
}