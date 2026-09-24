<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Transaksi;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;

/**
 * BookingObserver
 *
 * Memantau perubahan pada model Booking.
 *
 * LOGIKA EARN POIN:
 *  - Poin diberikan saat booking berstatus "Selesai" DAN transaksinya sudah "Lunas".
 *  - Jika booking "Selesai" tapi belum bayar, poin di-skip (tidak ada uang masuk).
 *  - Duplikasi dicegah oleh PointService::earn() via cek PointTransaction.
 */
class BookingObserver
{
    public function __construct(protected PointService $pointService)
    {
    }

    /**
     * Dipanggil setiap kali record Booking diupdate.
     */
    public function updated(Booking $booking): void
    {
        // Trigger EARN saat status berubah menjadi "Selesai"
        if (
            $booking->wasChanged('status_booking') &&
            $booking->status_booking === 'Selesai'
        ) {
            $this->triggerEarn($booking);
        }
    }

    /**
     * Proses penambahan poin dari booking yang selesai.
     *
     * Guard: hanya jalankan EARN jika transaksi sudah Lunas.
     * Ini mencegah poin diberikan pada booking yang belum dibayar.
     */
    private function triggerEarn(Booking $booking): void
    {
        // Pastikan ada pasien
        $pasien = $booking->pasien;
        if (!$pasien) {
            Log::warning("[BookingObserver] Booking #{$booking->id_booking} tidak memiliki pasien, skip earn.");
            return;
        }

        // Ambil transaksi terkait (lazy load jika belum di-load)
        $transaksi = $booking->transaksi ?? $booking->load('transaksi')->transaksi;

        // Guard: hanya berikan poin jika transaksi sudah Lunas
        $lunasStatuses = ['Lunas', 'lunas', 'settlement', 'capture'];
        if (!$transaksi || !in_array($transaksi->status_transaksi, $lunasStatuses)) {
            Log::info("[BookingObserver] Booking #{$booking->id_booking} status transaksi bukan Lunas ('{$transaksi?->status_transaksi}'), skip earn.");
            return;
        }

        // Tentukan nominal yang dipakai untuk menghitung poin.
        // Gunakan jumlah setelah dikurangi diskon poin yang sudah dipakai.
        $jumlahTotal = (float) ($transaksi->jumlah_total ?? 0);

        if ($jumlahTotal <= 0) {
            Log::info("[BookingObserver] Booking #{$booking->id_booking} jumlah_total = 0, skip earn.");
            return;
        }

        try {
            $result = $this->pointService->earn(
                pasien: $pasien,
                jumlahTransaksi: $jumlahTotal,
                booking: $booking,
                transaksi: $transaksi,
            );

            if ($result) {
                Log::info("[BookingObserver] EARN {$result->amount} poin untuk pasien #{$pasien->id_pasien} dari booking #{$booking->id_booking}.");
            }
        } catch (\Throwable $e) {
            // Jangan gagalkan proses booking hanya karena poin bermasalah
            Log::error("[BookingObserver] Gagal earn poin booking #{$booking->id_booking}: {$e->getMessage()}");
        }
    }
}
