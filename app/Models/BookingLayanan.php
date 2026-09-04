<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model BookingLayanan
 *
 * Pivot detail per layanan dalam satu booking.
 * Menyimpan rincian biaya SL, SB, HPP BHP, dan hak nakes
 * untuk masing-masing layanan yang dipesan.
 *
 * @property int    $id_booking
 * @property int    $id_layanan
 * @property int    $urutan          — urutan layanan (1 = utama)
 * @property float  $sl              — tarif jasa layanan
 * @property float  $sb              — biaya BHP
 * @property float  $hpp_bhp         — HPP BHP (modal)
 * @property float  $hak_nakes_layanan — fee nakes (tanpa transport)
 */
class BookingLayanan extends Model
{
    use HasFactory;

    protected $table = 'booking_layanan';

    protected $fillable = [
        'id_booking',
        'id_layanan',
        'urutan',
        'sl',
        'sb',
        'hpp_bhp',
        'hak_nakes_layanan',
    ];

    protected $casts = [
        'sl'                  => 'decimal:2',
        'sb'                  => 'decimal:2',
        'hpp_bhp'             => 'decimal:2',
        'hak_nakes_layanan'   => 'decimal:2',
        'urutan'              => 'integer',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    public function layanan()
    {
        return $this->belongsTo(MasterLayanan::class, 'id_layanan', 'id_layanan');
    }
}
