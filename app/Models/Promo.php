<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;
    
    protected $table = 'promos';
    protected $primaryKey = 'id_promo';

    protected $fillable = [
        'id_layanan',
        'deskripsi',
        'tipe_diskon',
        'nilai_diskon',
        'tanggal_mulai',
        'tanggal_berakhir',
        'status_promo',
        'gambar_promo',
    ];

    protected $casts = [
        'nilai_diskon' => 'decimal:2',
        'tanggal_mulai' => 'date',
        'tanggal_berakhir' => 'date',
    ];

    protected $appends = ['gambar_promo_url'];

    public function getGambarPromoUrlAttribute()
    {
        if (!$this->gambar_promo) {
            return null;
        }

        return url(\Illuminate\Support\Facades\Storage::disk('public')->url($this->gambar_promo));
    }

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }
}