<?php

namespace App\Observers;

use App\Models\Transaksi;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * TransaksiObserver
 *
 * Memantau perubahan status pada model Transaksi.
 * Mengirim notifikasi otomatis saat pembayaran berhasil (Lunas / settlement / capture).
 */
class TransaksiObserver
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    /**
     * Dipanggil setiap kali record Transaksi diupdate.
     */
    public function updated(Transaksi $transaksi): void
    {
        if ($transaksi->wasChanged('status_transaksi')) {
            $lunasStatuses = ['Lunas', 'lunas', 'settlement', 'capture'];
            if (in_array($transaksi->status_transaksi, $lunasStatuses)) {
                $this->triggerPaymentSuccessNotification($transaksi);
            }
        }
    }

    /**
     * Kirim notifikasi pembayaran sukses ke pasien.
     */
    private function triggerPaymentSuccessNotification(Transaksi $transaksi): void
    {
        try {
            $booking = $transaksi->booking;
            if (!$booking) {
                return;
            }

            $pasien = $booking->pasien;
            if (!$pasien || !$pasien->id_user) {
                return;
            }

            $kodeBooking = $booking->kode_booking ?? ('#' . $booking->id_booking);
            $totalFormat = 'Rp ' . number_format((float) $transaksi->jumlah_total, 0, ',', '.');
            $namaLayanan = $booking->layanan?->nama_layanan ?? 'Layanan HomeCare';

            $this->notificationService->sendByCode(
                templateCode: 'payment_success',
                userId: $pasien->id_user,
                userRole: 'pasien',
                variables: [
                    'booking_id'   => $kodeBooking,
                    'pasien_name'  => $pasien->nama_lengkap ?? 'Pasien',
                    'amount'       => $totalFormat,
                    'nama_layanan' => $namaLayanan,
                ],
                options: [
                    'action_url' => "/booking/{$booking->id_booking}",
                    'data'       => [
                        'id_booking'   => $booking->id_booking,
                        'id_transaksi' => $transaksi->id_transaksi,
                        'type'         => 'payment_success',
                        'jumlah_total' => (float) $transaksi->jumlah_total,
                    ],
                    'created_by' => 'system',
                ]
            );

            Log::info("[TransaksiObserver] Notifikasi pembayaran sukses dikirim ke pasien #{$pasien->id_user} untuk booking #{$booking->id_booking}");
        } catch (\Throwable $e) {
            Log::error("[TransaksiObserver] Gagal mengirim notifikasi pembayaran: " . $e->getMessage());
        }
    }
}
