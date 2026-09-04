<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model BookingBhp
 *
 * Menyiapkan pencatatan BHP (Bahan Habis Pakai) yang digunakan dalam satu booking,
 * termasuk kuantitas default dan kuantitas tambahan yang diinput oleh Nakes saat tindakan.
 */
class BookingBhp extends Model
{
    use HasFactory;

    protected $table = 'booking_bhp';
    protected $primaryKey = 'id_booking_bhp';

    protected $fillable = [
        'id_booking',
        'id_layanan',
        'id_bhp',
        'qty_default',
        'qty_real',
        'qty_tambahan',
        'harga_jual',
        'harga_modal',
        'total_sb_tambahan',
        'total_hpp_tambahan',
    ];

    protected $casts = [
        'qty_default'        => 'integer',
        'qty_real'           => 'integer',
        'qty_tambahan'       => 'integer',
        'harga_jual'         => 'decimal:2',
        'harga_modal'        => 'decimal:2',
        'total_sb_tambahan'  => 'decimal:2',
        'total_hpp_tambahan' => 'decimal:2',
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

    public function bhpItem()
    {
        return $this->belongsTo(BhpItem::class, 'id_bhp', 'id_bhp');
    }
}
