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

    protected $appends = ['gambar_promo_url', 'status_aktual'];

    public function getGambarPromoUrlAttribute()
    {
        if (!$this->gambar_promo) {
            return null;
        }

        return url(\Illuminate\Support\Facades\Storage::disk('public')->url($this->gambar_promo));
    }

    /**
     * Status aktual promo berdasarkan tanggal — mengabaikan kolom status_promo.
     * 'Aktif'      : status_promo = Aktif DAN sekarang dalam rentang tanggal
     * 'Kedaluwarsa': sudah melewati tanggal_berakhir
     * 'Belum Mulai': belum mencapai tanggal_mulai
     * 'Tidak Aktif': dinonaktifkan manual oleh admin
     */
    public function getStatusAktualAttribute(): string
    {
        if ($this->status_promo !== 'Aktif') {
            return 'Tidak Aktif';
        }

        $today = now()->startOfDay();

        if ($this->tanggal_berakhir && $today->gt($this->tanggal_berakhir)) {
            return 'Kedaluwarsa';
        }

        if ($this->tanggal_mulai && $today->lt($this->tanggal_mulai)) {
            return 'Belum Mulai';
        }

        return 'Aktif';
    }

    /**
     * Scope: hanya promo yang benar-benar aktif saat ini (tanggal valid + status Aktif).
     */
    public function scopeAktifSekarang($query)
    {
        return $query
            ->where('status_promo', 'Aktif')
            ->whereDate('tanggal_mulai', '<=', now()->toDateString())
            ->whereDate('tanggal_berakhir', '>=', now()->toDateString());
    }

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }
}