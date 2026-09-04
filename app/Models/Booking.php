<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Booking extends Model
{
    use HasFactory;

    protected $table = 'bookings';
    protected $primaryKey = 'id_booking';

    protected $fillable = [
        'booking_code',
        'medical_record_number',
        'id_pasien',
        'id_layanan',
        'id_tenaga_medis',
        'id_promo',
        'tanggal_kunjungan',
        'jam_kunjungan',
        'alamat_kunjungan',
        'latitude_kunjungan',
        'longitude_kunjungan',
        'status_booking',
        'catatan_penolakan',
    ];

    protected $casts = [
        'catatan_penolakan' => 'json',
    ];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'id_pasien', 'id_pasien');
    }

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }

    public function tenagaMedis()
    {
        return $this->belongsTo(TenagaMedis::class, 'id_tenaga_medis', 'id_tenaga_medis');
    }

    public function transaksi()
    {
        return $this->hasOne(Transaksi::class, 'id_booking', 'id_booking');
    }

    /**
     * Detail per-layanan dalam booking (multi-layanan).
     * Diurutkan berdasarkan kolom `urutan` (layanan utama = 1).
     */
    public function layananItems()
    {
        return $this->hasMany(BookingLayanan::class, 'id_booking', 'id_booking')
                    ->orderBy('urutan');
    }

    /**
     * Detail BHP yang digunakan dalam booking (termasuk kuantitas tambahan dari nakes).
     */
    public function bookingBhp()
    {
        return $this->hasMany(BookingBhp::class, 'id_booking', 'id_booking');
    }

    /**
     * Generate nomor rekam medis dengan format P-YY-XXXX
     * P = Pasien
     * YY = 2 digit tahun terakhir (26 untuk 2026)
     * XXXX = nomor urut per pasien per tahun
     */
    public static function generateMedicalRecordNumber(): string
{
    $year = now()->year;
    $yearLast2 = str_pad($year % 100, 2, '0', STR_PAD_LEFT);
    $prefix = "P-{$yearLast2}-";


    $lastBooking = self::where('medical_record_number', 'LIKE', $prefix . '%')
        ->orderBy('id_booking', 'desc')
        ->lockForUpdate()
        ->first();

    if ($lastBooking && $lastBooking->medical_record_number) {
        $lastSequence = (int) substr($lastBooking->medical_record_number, -4);
        $sequence = $lastSequence + 1;
    } else {
        $sequence = 1;
    }

    $sequenceStr = str_pad($sequence, 4, '0', STR_PAD_LEFT);

    return "{$prefix}{$sequenceStr}";
}
}
