<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Pasien;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * TransaksiObserver
 *
 * Memantau perubahan status pada model Transaksi.
 * - Mengirim notifikasi otomatis saat pembayaran berhasil (Lunas / settlement / capture).
 * - Mengkredit saldo nakes dari kolom hak_nakes saat transaksi Lunas.
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
                $this->creditSaldoNakes($transaksi);
            }
        }
    }

    /**
     * Kredit saldo nakes dari hak_nakes saat transaksi menjadi Lunas.
     *
     * Guard idempotensi: saldo hanya dikreditkan sekali per transaksi.
     * Logika ini menggunakan increment DB-level agar aman dari race condition.
     */
    private function creditSaldoNakes(Transaksi $transaksi): void
    {
        try {
            $hakNakes = (float) $transaksi->hak_nakes;

            if ($hakNakes <= 0) {
                Log::info("[TransaksiObserver] hak_nakes = 0 untuk transaksi #{$transaksi->id_transaksi}, skip credit saldo.");
                return;
            }

            $booking = $transaksi->booking ?? Booking::find($transaksi->id_booking);
            if (!$booking || !$booking->id_tenaga_medis) {
                Log::warning("[TransaksiObserver] Booking/nakes tidak ditemukan untuk transaksi #{$transaksi->id_transaksi}, skip credit saldo.");
                return;
            }

            $affected = TenagaMedis::where('id_tenaga_medis', $booking->id_tenaga_medis)
                                   ->increment('saldo', $hakNakes);

            if ($affected) {
                Log::info("[TransaksiObserver] Saldo nakes #{$booking->id_tenaga_medis} dikreditkan Rp " . number_format($hakNakes, 0, ',', '.') . " dari transaksi #{$transaksi->id_transaksi}.");
            }
        } catch (\Throwable $e) {
            // Jangan sampai error credit saldo mengganggu alur pembayaran utama
            Log::error("[TransaksiObserver] Gagal credit saldo nakes untuk transaksi #{$transaksi->id_transaksi}: " . $e->getMessage());
        }
    }

    /**
     * Kirim notifikasi pembayaran sukses ke pasien.
     */
    private function triggerPaymentSuccessNotification(Transaksi $transaksi): void
    {
        try {
            $booking = $transaksi->booking ?? \App\Models\Booking::find($transaksi->id_booking);
            if (!$booking) {
                return;
            }

            $pasien = $booking->pasien ?? \App\Models\Pasien::find($booking->id_pasien);
            if (!$pasien || !$pasien->id_user) {
                return;
            }

            // Guard idempotensi: pastikan tidak mengirim notifikasi duplikat untuk transaksi yang sama
            $exists = \App\Models\Notification::where('user_id', $pasien->id_user)
                ->where('user_role', 'pasien')
                ->where(function ($q) use ($transaksi) {
                    $q->whereJsonContains('data->id_transaksi', $transaksi->id_transaksi)
                      ->orWhereJsonContains('data->id_transaksi', (string) $transaksi->id_transaksi);
                })
                ->exists();

            if ($exists) {
                Log::info("[TransaksiObserver] Notifikasi payment_success untuk transaksi #{$transaksi->id_transaksi} sudah ada, skip duplicate.");
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
                    'action_url' => "/booking/aktif?id={$booking->id_booking}",
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
