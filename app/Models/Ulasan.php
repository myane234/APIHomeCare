<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ulasan extends Model
{
    use HasFactory;

    protected $table = 'ulasans';

    protected $fillable = [
        'nama_pengulas',
        'profesi_peran',
        'foto',
        'rating',
        'komentar',
        'layanan_id',
        'is_published',
        'urutan',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_published' => 'boolean',
        'urutan' => 'integer',
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
}
