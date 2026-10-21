<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Seed default notification templates into the database.
     */
    public function run(): void
    {
        $templates = [
            [
                'code'         => 'payment_success',
                'name'         => 'Pembayaran Berhasil',
                'target_role'  => 'pasien',
                'trigger_type' => 'transaksi_paid',
                'title'        => 'Pembayaran Berhasil',
                'body'         => 'Pembayaran sebesar {amount} untuk booking {booking_id} telah berhasil diterima. Pesanan Anda akan segera diproses.',
                'action_url'   => '/booking/aktif?id={booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_created',
                'name'         => 'Booking Dibuat',
                'target_role'  => 'pasien',
                'trigger_type' => 'booking_created',
                'title'        => 'Booking Berhasil Dibuat',
                'body'         => 'Pesanan booking {booking_id} ({nama_layanan}) berhasil dibuat. Silakan selesaikan pembayaran untuk memproses pesanan.',
                'action_url'   => '/booking/aktif?id={booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_assigned',
                'name'         => 'Penugasan Nakes Baru',
                'target_role'  => 'nakes',
                'trigger_type' => 'booking_assigned',
                'title'        => 'Pesanan Layanan Baru',
                'body'         => 'Anda memiliki pesanan layanan baru {booking_id} ({nama_layanan}) dari pasien {pasien_name}.',
                'action_url'   => '/nakes/dashboard',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_diproses',
                'name'         => 'Booking Diproses',
                'target_role'  => 'pasien',
                'trigger_type' => 'booking_status',
                'title'        => 'Booking Sedang Diproses',
                'body'         => 'Booking {booking_id} Anda telah dikonfirmasi dan sedang dipersiapkan oleh tenaga medis.',
                'action_url'   => '/booking/aktif?id={booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_diperjalanan',
                'name'         => 'Tenaga Medis Menuju Lokasi',
                'target_role'  => 'pasien',
                'trigger_type' => 'booking_status',
                'title'        => 'Tenaga Medis Menuju Lokasi',
                'body'         => 'Tenaga medis {nakes_name} sedang dalam perjalanan menuju lokasi Anda untuk booking {booking_id}.',
                'action_url'   => '/booking/aktif?id={booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_tindakan',
                'name'         => 'Pelayanan Sedang Berlangsung',
                'target_role'  => 'pasien',
                'trigger_type' => 'booking_status',
                'title'        => 'Pelayanan Sedang Berlangsung',
                'body'         => 'Tenaga medis telah tiba di lokasi dan sedang melakukan tindakan pelayanan untuk booking {booking_id}.',
                'action_url'   => '/booking/aktif?id={booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_selesai',
                'name'         => 'Layanan Selesai & Invoice Terbit',
                'target_role'  => 'pasien',
                'trigger_type' => 'booking_status',
                'title'        => 'Layanan Selesai',
                'body'         => 'Pelayanan untuk booking {booking_id} telah selesai. Bukti invoice telah terbit.',
                'action_url'   => '/transaksi/{booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_selesai_nakes',
                'name'         => 'Layanan Selesai (Nakes)',
                'target_role'  => 'nakes',
                'trigger_type' => 'booking_status',
                'title'        => 'Layanan Selesai',
                'body'         => 'Pelayanan untuk booking {booking_id} telah selesai. Terima kasih atas dedikasi Anda.',
                'action_url'   => '/nakes/riwayat-kunjungan/detail?id={booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_dibatalkan',
                'name'         => 'Booking Dibatalkan',
                'target_role'  => 'pasien',
                'trigger_type' => 'booking_status',
                'title'        => 'Booking Dibatalkan',
                'body'         => 'Pesanan booking {booking_id} telah dibatalkan.',
                'action_url'   => '/transaksi/{booking_id}',
                'channel'      => 'all',
                'is_active'    => true,
            ],
            [
                'code'         => 'booking_dibatalkan_nakes',
                'name'         => 'Booking Dibatalkan (Nakes)',
                'target_role'  => 'nakes',
                'trigger_type' => 'booking_status',
                'title'        => 'Booking Dibatalkan',
                'body'         => 'Pesanan booking {booking_id} telah dibatalkan oleh pasien/sistem.',
                'action_url'   => '/nakes/riwayat-kunjungan',
                'channel'      => 'all',
                'is_active'    => true,
            ],
        ];

        foreach ($templates as $data) {
            NotificationTemplate::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
