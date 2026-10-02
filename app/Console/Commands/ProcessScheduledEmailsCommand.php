<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\NotificationSchedule;
use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memproses pengiriman email dengan trigger waktu (Reminder H-1, Follow-up H+1, dan Jadwal Template)';

    /**
     * Execute the console command.
     */
    public function handle(EmailNotificationService $emailService): int
    {
        $this->info('Memulai pemrosesan email trigger waktu...');

        $this->processBookingReminders($emailService);
        $this->processPostTreatmentFollowups($emailService);
        $this->processTemplateSchedules($emailService);

        $this->info('Pemrosesan email selesai.');
        return Command::SUCCESS;
    }

    /**
     * 1. Trigger Waktu: Reminder H-1 Booking Kunjungan
     */
    protected function processBookingReminders(EmailNotificationService $emailService): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $bookings = Booking::with(['pasien.user', 'tenagaMedis'])
            ->whereDate('tanggal_booking', $tomorrow)
            ->whereIn('status_booking', ['Dikonfirmasi', 'Menunggu Tenaga Medis', 'Dalam Perjalanan'])
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $pasien = $booking->pasien;
            $user   = $pasien?->user;
            if (!$user?->email) {
                continue;
            }

            // Cegah duplikasi pengingat untuk booking yang sama
            $alreadySent = EmailLog::where('trigger_type', 'booking_reminder')
                ->where('reference_id', $booking->id_booking)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $kodeBooking = $booking->kode_booking ?? ('#' . $booking->id_booking);
            $namaNakes   = $booking->tenagaMedis?->nama_lengkap ?? 'Tenaga Medis';
            $jamLayanan  = $booking->jam_booking ?? 'Sesuai Jadwal';

            $subject = "Pengingat Jadwal Pelayanan Besok: {$kodeBooking}";
            $body = "Halo {$pasien->nama_lengkap},\n\n"
                  . "Ini adalah pengingat bahwa Anda memiliki jadwal pelayanan HomeCare besok pada tanggal "
                  . Carbon::parse($booking->tanggal_booking)->translatedFormat('d F Y') . " ({$jamLayanan}).\n\n"
                  . "Tenaga Medis: {$namaNakes}\n"
                  . "Lokasi Kunjungan: " . ($booking->alamat_layanan ?? $pasien->alamat_utama ?? '-') . "\n\n"
                  . "Mohon pastikan Anda berada di lokasi pada waktu yang telah ditentukan. Terima kasih!";

            $sent = $emailService->sendDynamicEmail(
                toEmail: $user->email,
                subject: $subject,
                bodyContent: $body,
                options: [
                    'user_id'        => $user->id_user,
                    'recipient_name' => $pasien->nama_lengkap,
                    'trigger_type'   => 'booking_reminder',
                    'reference_id'   => $booking->id_booking,
                    'reference_type' => 'booking',
                    'action_url'     => "/booking/{$booking->id_booking}",
                    'action_text'    => 'Lihat Detail Booking',
                    'created_by'     => 'scheduler',
                ]
            );

            if ($sent) {
                $count++;
            }
        }

        $this->info("Reminder H-1 terkirim: {$count} email.");
    }

    /**
     * 2. Trigger Waktu: Follow-up H+1 setelah Tindakan Selesai
     */
    protected function processPostTreatmentFollowups(EmailNotificationService $emailService): void
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $bookings = Booking::with(['pasien.user', 'tenagaMedis'])
            ->where('status_booking', 'Selesai')
            ->whereDate('updated_at', $yesterday)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $pasien = $booking->pasien;
            $user   = $pasien?->user;
            if (!$user?->email) {
                continue;
            }

            // Cegah duplikasi
            $alreadySent = EmailLog::where('trigger_type', 'post_treatment_followup')
                ->where('reference_id', $booking->id_booking)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $kodeBooking = $booking->kode_booking ?? ('#' . $booking->id_booking);
            $subject = "Bagaimana Kondisi Anda Hari Ini? - Follow-up Booking {$kodeBooking}";
            $body = "Halo {$pasien->nama_lengkap},\n\n"
                  . "Kemarin Anda telah menyelesaikan pelayanan HomeCare bersama kami. Bagaimana kondisi kesehatan Anda hari ini?\n\n"
                  . "Jika Anda membutuhkan konsultasi lanjutan atau memiliki pertanyaan terkait proses pemulihan, jangan ragu untuk menghubungi layanan kami melalui aplikasi.\n\n"
                  . "Semoga lekas pulih dan sehat selalu!";

            $sent = $emailService->sendDynamicEmail(
                toEmail: $user->email,
                subject: $subject,
                bodyContent: $body,
                options: [
                    'user_id'        => $user->id_user,
                    'recipient_name' => $pasien->nama_lengkap,
                    'trigger_type'   => 'post_treatment_followup',
                    'reference_id'   => $booking->id_booking,
                    'reference_type' => 'booking',
                    'action_url'     => "/booking/{$booking->id_booking}/review",
                    'action_text'    => 'Beri Ulasan Layanan',
                    'created_by'     => 'scheduler',
                ]
            );

            if ($sent) {
                $count++;
            }
        }

        $this->info("Follow-up H+1 terkirim: {$count} email.");
    }

    /**
     * 3. Trigger Jadwal Template Terjadwal dari tabel notification_schedules
     */
    protected function processTemplateSchedules(EmailNotificationService $emailService): void
    {
        $activeSchedules = NotificationSchedule::with('template')
            ->where('is_active', true)
            ->whereHas('template', fn ($q) => $q->where('is_active', true)->whereIn('channel', ['email', 'all']))
            ->get();

        $count = 0;
        foreach ($activeSchedules as $schedule) {
            $template = $schedule->template;
            if (!$template) {
                continue;
            }

            // Kirim broadcast template jika trigger_type jadwal aktif
            if ($template->trigger_type === 'promo' || $template->trigger_type === 'scheduled') {
                $sent = $emailService->broadcastEmail(
                    targetRole: $template->target_role ?? 'all',
                    subject: $template->title,
                    bodyContent: $template->body,
                    options: [
                        'template_id'  => $template->id,
                        'action_url'   => $template->action_url,
                        'created_by'   => 'schedule_cron',
                    ]
                );
                $count += $sent;
            }
        }

        $this->info("Email jadwal template terkirim: {$count} email.");
    }
}
