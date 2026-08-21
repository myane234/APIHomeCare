<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kecamatan extends Model
{
    use HasFactory;

    protected $table = 'master_kecamatan';
    protected $primaryKey = 'id_kecamatan';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kecamatan',
        'regency_id',
        'nama_kecamatan',
    ];

    protected $appends = [
        'nama_regency',
    ];

    public function getNamaRegencyAttribute()
    {
        return $this->kotaKabupaten?->nama_kota;
    }

    public function kotaKabupaten()
    {
        return $this->belongsTo(KotaKabupaten::class, 'regency_id', 'id_kota'); 
    }

    public function kota_kabupaten()
    {
        return $this->belongsTo(KotaKabupaten::class, 'regency_id', 'id_kota'); 
    }

    public function kelurahans()
    {
        return $this->hasMany(Kelurahan::class, 'id_kecamatan', 'id_kecamatan');
    }
}