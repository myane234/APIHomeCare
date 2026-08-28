<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Transaksi
 *
 * Merepresentasikan tabel transaksis.
 * Kolom-kolom sl, sb, st, ba, ppn, dll. adalah SNAPSHOT dari
 * kalkulasi biaya saat booking dibuat — nilai-nilai ini tidak berubah
 * meskipun template master_tarif dimodifikasi di kemudian hari.
 */
class Transaksi extends Model
{
    use HasFactory;

    protected $table      = 'transaksis';
    protected $primaryKey = 'id_transaksi';

    protected $fillable = [
        // Informasi utama
        'id_booking',
        'jumlah_total',
        'metode_pembayaran',
        'status_transaksi',
        'waktu_bayar',

        // Snapshot komponen biaya
        'sl',               // Tarif Layanan / Jasa
        'sb',               // Tarif BHP (Bahan Habis Pakai)
        'st',               // Tarif Transport
        'ba',               // Biaya Administrasi
        'ppn',              // Nominal PPN

        // Snapshot persentase dari master_tarif
        'persen_ppn',
        'persen_fee_nakes',

        // Biaya lain
        'fee_midtrans',
        'hpp_bhp',

        // Bagi hasil
        'hak_nakes',
        'profit_hc',

        // Payment details dari Midtrans
        'midtrans_transaction_id',
        'midtrans_order_id',
        'qr_string',
        'qr_url',
        'va_number',
        'bank_va',
        'payment_method',
        'midtrans_response',
    ];

    protected $casts = [
        'jumlah_total'     => 'decimal:2',
        'sl'               => 'decimal:2',
        'sb'               => 'decimal:2',
        'st'               => 'decimal:2',
        'ba'               => 'decimal:2',
        'ppn'              => 'decimal:2',
        'persen_ppn'       => 'decimal:2',
        'persen_fee_nakes' => 'decimal:2',
        'fee_midtrans'     => 'decimal:2',
        'hpp_bhp'          => 'decimal:2',
        'hak_nakes'        => 'decimal:2',
        'profit_hc'        => 'decimal:2',
        'waktu_bayar'      => 'datetime',
        'midtrans_response' => 'json',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }
}
