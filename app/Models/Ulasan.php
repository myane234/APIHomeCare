<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ulasan extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'ulasans';

    protected $fillable = [
        'id_user',
        'id_booking',
        'id_tenaga_medis',
        'nama_pengulas',
        'email',
        'profesi_peran',
        'foto',
        'rating',
        'komentar',
        'quick_tags',
        'layanan_id',
        'is_published',
        'urutan',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_published' => 'boolean',
        'urutan' => 'integer',
        'quick_tags' => 'array',
    ];

    protected $appends = [
        'foto_url',
    ];

    /**
     * Get full URL for foto avatar
     */
    public function getFotoUrlAttribute()
    {
        if (!$this->foto) {
            return null;
        }

        if (str_starts_with($this->foto, 'http://') || str_starts_with($this->foto, 'https://')) {
            return $this->foto;
        }

        return url(Storage::url($this->foto));
    }

    /**
     * Relasi ke Layanan (opsional)
     */
    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'layanan_id');
    }

    /**
     * Relasi ke User
     */
    public function user()
    {
        return $this->belongsTo(Users::class, 'id_user', 'id_user');
    }

    /**
     * Relasi ke Booking
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    /**
     * Relasi ke Tenaga Medis
     */
    public function tenagaMedis()
    {
        return $this->belongsTo(TenagaMedis::class, 'id_tenaga_medis', 'id_tenaga_medis');
    }
}
