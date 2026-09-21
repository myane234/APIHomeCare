<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiTambahan extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'transaksi_tambahan';
    protected $primaryKey = 'id_transaksi_tambahan';

    protected $fillable = [
        'id_booking',
        'kode_booking',
        'midtrans_order_id',
        'jumlah_total',
        'metode_pembayaran',
        'status_transaksi',
        'waktu_bayar',
        'sb_tambahan',
        'hpp_bhp_tambahan',
        'midtrans_transaction_id',
        'qr_string',
        'qr_url',
        'va_number',
        'bank_va',
        'payment_method',
        'midtrans_response',
    ];

    protected $casts = [
        'jumlah_total' => 'decimal:2',
        'sb_tambahan' => 'decimal:2',
        'hpp_bhp_tambahan' => 'decimal:2',
        'waktu_bayar' => 'datetime',
        'midtrans_response' => 'json',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }
}
