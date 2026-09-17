<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatKunjungan extends Model
{
    use HasFactory;

    protected $table = 'riwayat_kunjungan';
    protected $primaryKey = 'id_riwayat_kunjungan';

    protected $fillable = [
        'id_booking',
        'id_tenaga_medis',
        'status_kunjungan',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    public function tenagaMedis()
    {
        return $this->belongsTo(TenagaMedis::class, 'id_tenaga_medis', 'id_tenaga_medis');
    }
}
