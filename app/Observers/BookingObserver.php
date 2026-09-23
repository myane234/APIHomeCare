<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;

/**
 * BookingObserver
 *
 * Memantau perubahan pada model Booking.
 * Trigger EARN poin saat status_booking berubah menjadi "Selesai"
 * dan booking memiliki transaksi yang sudah Paid.
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
        // Hanya proses jika status_booking baru saja berubah menjadi "Selesai"
        if (
            $booking->wasChanged('status_booking') &&
            $booking->status_booking === 'Selesai'
        ) {
            $this->triggerEarn($booking);
        }
    }

    /**
     * Proses penambahan poin dari booking yang selesai.
     */
    private function triggerEarn(Booking $booking): void
    {
        // Pastikan ada pasien
        $pasien = $booking->pasien;
        if (!$pasien) {
            Log::warning("[BookingObserver] Booking #{$booking->id_booking} tidak memiliki pasien, skip earn.");
            return;
        }

        // Ambil transaksi terkait
        $transaksi = $booking->transaksi;

        // Tentukan nominal yang dipakai untuk menghitung poin.
        // Prioritas: transaksi utama (jumlah_total), fallback 0.
        $jumlahTotal = $transaksi?->jumlah_total ?? 0;

        if ($jumlahTotal <= 0) {
            Log::info("[BookingObserver] Booking #{$booking->id_booking} jumlah_total = 0, skip earn.");
            return;
        }

        try {
            $result = $this->pointService->earn(
                pasien: $pasien,
                jumlahTransaksi: (float) $jumlahTotal,
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
