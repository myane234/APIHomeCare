<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Transaksi;
use App\Services\EmailNotificationService;
use App\Services\NotificationService;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;

/**
 * BookingObserver
 *
 * Memantau event lifecycle pada model Booking.
 *
 * TRIGGER AKSI (Action-Based):
 *  1. Booking Dibuat (created) -> Kirim In-App Notification ke Pasien & Nakes.
 *  2. Booking Diupdate (updated) -> Kirim In-App Notification sesuai status:
 *     - Diproses / Dikonfirmasi
 *     - DiPerjalanan (Nakes menuju lokasi)
 *     - Tindakan (Tindakan dimulai)
 *     - Selesai (Earn Poin, Email Invoice, Notifikasi In-App)
 *     - Dibatalkan
 */
class BookingObserver
{
    public function __construct(
        protected PointService $pointService,
        protected EmailNotificationService $emailService,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Dipanggil setiap kali record Booking baru dibuat (booking_created).
     */
    public function created(Booking $booking): void
    {
        $this->triggerBookingCreatedNotification($booking);
    }

    /**
     * Dipanggil setiap kali record Booking diupdate.
     */
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status_booking')) {
            $newStatus = $booking->status_booking;

            switch ($newStatus) {
                case 'Selesai':
                    $this->triggerEarn($booking);
                    $this->triggerInvoiceEmail($booking);
                    $this->triggerStatusNotification(
                        $booking,
                        'Layanan Selesai & Invoice Telah Terbit',
                        'Pelayanan telah selesai. Bukti invoice telah dikirimkan ke email Anda.',
                        'booking_selesai'
                    );
                    break;

                case 'Diproses':
                case 'Dikonfirmasi':
                    $this->triggerStatusNotification(
                        $booking,
                        'Booking Sedang Diproses',
                        'Booking Anda telah dikonfirmasi dan sedang dipersiapkan oleh tenaga medis.',
                        'booking_diproses'
                    );
                    break;

                case 'DiPerjalanan':
                case 'Dalam Perjalanan':
                    $namaNakes = $booking->tenagaMedis?->nama_lengkap ?? 'Tenaga medis';
                    $this->triggerStatusNotification(
                        $booking,
                        'Tenaga Medis Menuju Lokasi',
                        "{$namaNakes} sedang dalam perjalanan menuju lokasi Anda.",
                        'booking_diperjalanan'
                    );
                    break;

                case 'Tindakan':
                    $this->triggerStatusNotification(
                        $booking,
                        'Pelayanan Sedang Berlangsung',
                        'Tenaga medis telah tiba di lokasi dan sedang melakukan tindakan pelayanan.',
                        'booking_tindakan'
                    );
                    break;

                case 'Dibatalkan':
                    $this->triggerStatusNotification(
                        $booking,
                        'Booking Dibatalkan',
                        'Pesanan booking Anda telah dibatalkan.',
                        'booking_dibatalkan'
                    );
                    break;
            }
        }
    }

    /**
     * Notifikasi saat booking baru berhasil dibuat.
     */
    private function triggerBookingCreatedNotification(Booking $booking): void
    {
        try {
            $booking->loadMissing(['pasien.user', 'tenagaMedis.user', 'layanan']);
            $pasien = $booking->pasien;
            $kodeBooking = $booking->kode_booking ?? ('#' . $booking->id_booking);
            $namaLayanan = $booking->layanan?->nama_layanan ?? 'Layanan HomeCare';

            // 1. Notifikasi ke Pasien (booking_created)
            if ($pasien && $pasien->id_user) {
                $this->notificationService->sendByCode(
                    templateCode: 'booking_created',
                    userId: $pasien->id_user,
                    userRole: 'pasien',
                    variables: [
                        'booking_id'   => $kodeBooking,
                        'pasien_name'  => $pasien->nama_lengkap ?? 'Pasien',
                        'nama_layanan' => $namaLayanan,
                    ],
                    options: [
                        'action_url' => "/booking/{$booking->id_booking}",
                        'data'       => ['id_booking' => $booking->id_booking, 'type' => 'booking_created'],
                        'created_by' => 'system',
                    ]
                );
            }

            // 2. Notifikasi ke Nakes jika nakes sudah ditentukan (booking_assigned)
            $nakes = $booking->tenagaMedis;
            if ($nakes && $nakes->id_user) {
                $this->notificationService->sendByCode(
                    templateCode: 'booking_assigned',
                    userId: $nakes->id_user,
                    userRole: 'nakes',
                    variables: [
                        'booking_id'   => $kodeBooking,
                        'nakes_name'   => $nakes->nama_lengkap ?? 'Tenaga Medis',
                        'pasien_name'  => $pasien?->nama_lengkap ?? 'Pasien',
                        'nama_layanan' => $namaLayanan,
                    ],
                    options: [
                        'action_url' => "/nakes/booking/{$booking->id_booking}",
                        'data'       => ['id_booking' => $booking->id_booking, 'type' => 'booking_assigned'],
                        'created_by' => 'system',
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error("[BookingObserver] Gagal kirim notifikasi booking_created #{$booking->id_booking}: {$e->getMessage()}");
        }
    }

    /**
     * Helper untuk kirim notifikasi perubahan status booking ke pasien & nakes.
     */
    private function triggerStatusNotification(Booking $booking, string $title, string $bodySuffix, string $type): void
    {
        try {
            $booking->loadMissing(['pasien.user', 'tenagaMedis.user', 'layanan']);
            $pasien = $booking->pasien;
            $nakes  = $booking->tenagaMedis;
            $kodeBooking = $booking->kode_booking ?? ('#' . $booking->id_booking);
            $namaNakes = $nakes?->nama_lengkap ?? 'Tenaga Medis';
            $namaLayanan = $booking->layanan?->nama_layanan ?? 'Layanan HomeCare';

            $variables = [
                'booking_id'   => $kodeBooking,
                'pasien_name'  => $pasien?->nama_lengkap ?? 'Pasien',
                'nakes_name'   => $namaNakes,
                'nama_layanan' => $namaLayanan,
            ];

            // Notifikasi ke Pasien
            if ($pasien && $pasien->id_user) {
                $this->notificationService->sendByCode(
                    templateCode: $type,
                    userId: $pasien->id_user,
                    userRole: 'pasien',
                    variables: $variables,
                    options: [
                        'action_url' => "/booking/{$booking->id_booking}",
                        'data'       => ['id_booking' => $booking->id_booking, 'status' => $booking->status_booking, 'type' => $type],
                        'created_by' => 'system',
                    ]
                );
            }

            // Notifikasi ke Nakes jika status Dibatalkan atau Selesai
            if ($nakes && $nakes->id_user && in_array($type, ['booking_dibatalkan', 'booking_selesai'])) {
                $this->notificationService->sendByCode(
                    templateCode: $type,
                    userId: $nakes->id_user,
                    userRole: 'nakes',
                    variables: $variables,
                    options: [
                        'action_url' => "/nakes/booking/{$booking->id_booking}",
                        'data'       => ['id_booking' => $booking->id_booking, 'status' => $booking->status_booking, 'type' => $type],
                        'created_by' => 'system',
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error("[BookingObserver] Gagal kirim notifikasi status booking #{$booking->id_booking}: {$e->getMessage()}");
        }
    }

    /**
     * Trigger pengiriman email invoice resmi ke pasien.
     */
    private function triggerInvoiceEmail(Booking $booking): void
    {
        try {
            $this->emailService->sendInvoice($booking, createdBy: 'booking_observer');
        } catch (\Throwable $e) {
            Log::error("[BookingObserver] Gagal trigger email invoice #{$booking->id_booking}: {$e->getMessage()}");
        }
    }

    /**
     * Proses penambahan poin dari booking yang selesai.
     *
     * Guard: hanya jalankan EARN jika transaksi sudah Lunas.
     */
    private function triggerEarn(Booking $booking): void
    {
        $pasien = $booking->pasien;
        if (!$pasien) {
            Log::warning("[BookingObserver] Booking #{$booking->id_booking} tidak memiliki pasien, skip earn.");
            return;
        }

        $transaksi = $booking->transaksi ?? $booking->load('transaksi')->transaksi;

        $lunasStatuses = ['Lunas', 'lunas', 'settlement', 'capture'];
        if (!$transaksi || !in_array($transaksi->status_transaksi, $lunasStatuses)) {
            Log::info("[BookingObserver] Booking #{$booking->id_booking} status transaksi bukan Lunas ('{$transaksi?->status_transaksi}'), skip earn.");
            return;
        }

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
            Log::error("[BookingObserver] Gagal earn poin booking #{$booking->id_booking}: {$e->getMessage()}");
        }
    }
}
