<?php

namespace App\Services;

use App\Mail\DynamicNotificationMail;
use App\Mail\InvoiceMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\NotificationTemplate;
use App\Models\Users;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EmailNotificationService
 *
 * Mengelola pengiriman email via Resend / Mail driver Laravel:
 *   - sendInvoice()       : Kirim email invoice resmi saat booking selesai (Action-Based Trigger)
 *   - sendDynamicEmail()  : Kirim email notifikasi / promo / pengingat manual
 *   - sendByTemplate()    : Kirim email berdasarkan template dari database dengan variabel dinamis
 *   - broadcastEmail()    : Broadcast email ke seluruh pasien / nakes aktif
 *   - sendReminder()      : Kirim pengingat jadwal booking (Time-Based Trigger)
 */
class EmailNotificationService
{
    /**
     * Kirim email invoice booking selesai ke pasien (Action-Based Trigger).
     *
     * @param  Booking  $booking
     * @param  string   $createdBy
     * @return bool
     */
    public function sendInvoice(Booking $booking, string $createdBy = 'system'): bool
    {
        try {
            $booking->loadMissing([
                'pasien.user',
                'tenagaMedis.user',
                'bookingLayanan.layanan',
                'bookingBhp.bhp',
                'transaksi',
            ]);

            $pasien = $booking->pasien;
            $user   = $pasien?->user;
            $email  = $user?->email;

            if (!$email) {
                Log::warning("[EmailNotificationService] Pasien booking #{$booking->id_booking} tidak memiliki alamat email valid.");
                return false;
            }

            // Susun rincian item (layanan & BHP)
            $items = [];
            foreach ($booking->bookingLayanan ?? [] as $bl) {
                $items[] = [
                    'nama'  => $bl->layanan?->nama_layanan ?? 'Layanan Homecare',
                    'qty'   => 1,
                    'harga' => (float) ($bl->harga ?? 0),
                ];
            }
            foreach ($booking->bookingBhp ?? [] as $bb) {
                $items[] = [
                    'nama'  => $bb->bhp?->nama_bhp ?? 'BHP / Obat',
                    'qty'   => (int) ($bb->qty ?? 1),
                    'harga' => (float) ($bb->subtotal ?? 0),
                ];
            }

            $transaksi = $booking->transaksi;
            $totalBayar = $transaksi ? (float) $transaksi->jumlah_total : (float) ($booking->total_biaya ?? 0);
            $diskonPoin = $transaksi ? (float) ($transaksi->poin_diskon_nominal ?? 0) : 0;
            $biayaTransport = (float) ($booking->biaya_transport ?? 0);

            $invoiceData = [
                'id_booking'         => $booking->id_booking,
                'kode_booking'       => $booking->kode_booking ?? ('BK-' . str_pad((string) $booking->id_booking, 5, '0', STR_PAD_LEFT)),
                'nama_pasien'        => $pasien->nama_lengkap ?? $user->email,
                'email_pasien'       => $email,
                'nama_nakes'         => $booking->tenagaMedis?->nama_lengkap ?? 'Tenaga Medis',
                'jenis_nakes'        => $booking->tenagaMedis?->jenis_tenaga_medis ?? 'Nakes',
                'tanggal_pelayanan'  => $booking->tanggal_booking ? date('d F Y', strtotime($booking->tanggal_booking)) : date('d F Y'),
                'alamat_pelayanan'   => $booking->alamat_layanan ?? $pasien->alamat_utama ?? '-',
                'metode_pembayaran'  => $transaksi?->metode_pembayaran ?? 'Online Payment',
                'status_pembayaran'  => strtoupper($transaksi?->status_transaksi ?? 'LUNAS'),
                'items'              => $items,
                'biaya_transport'    => $biayaTransport,
                'diskon_poin'        => $diskonPoin,
                'total_bayar'        => $totalBayar,
                'catatan_tindakan'   => $booking->catatan_nakes ?? $booking->catatan ?? null,
            ];

            // Kirim email via Mail driver (Resend)
            Mail::to($email, $invoiceData['nama_pasien'])->send(new InvoiceMail($invoiceData));

            // Log riwayat pengiriman
            EmailLog::create([
                'user_id'         => $user->id_user,
                'recipient_email' => $email,
                'recipient_name'  => $invoiceData['nama_pasien'],
                'subject'         => "Invoice & Bukti Pelayanan {$invoiceData['kode_booking']}",
                'trigger_type'    => 'booking_selesai_invoice',
                'reference_id'    => $booking->id_booking,
                'reference_type'  => 'booking',
                'status'          => 'sent',
                'payload'         => $invoiceData,
                'sent_at'         => now(),
                'created_by'      => $createdBy,
            ]);

            Log::info("[EmailNotificationService] Email invoice berhasil dikirim ke {$email} untuk booking #{$booking->id_booking}.");
            return true;
        } catch (\Throwable $e) {
            Log::error("[EmailNotificationService] Gagal mengirim email invoice booking #{$booking->id_booking}: " . $e->getMessage());

            // Catat log gagal jika email pasien diketahui
            if (!empty($email)) {
                EmailLog::create([
                    'user_id'         => $user?->id_user,
                    'recipient_email' => $email,
                    'recipient_name'  => $pasien?->nama_lengkap,
                    'subject'         => "Invoice & Bukti Pelayanan #{$booking->id_booking}",
                    'trigger_type'    => 'booking_selesai_invoice',
                    'reference_id'    => $booking->id_booking,
                    'reference_type'  => 'booking',
                    'status'          => 'failed',
                    'error_message'   => $e->getMessage(),
                    'created_by'      => $createdBy,
                ]);
            }

            return false;
        }
    }

    /**
     * Kirim email dinamis / umum ke satu penerima.
     *
     * @param  string       $toEmail
     * @param  string       $subject
     * @param  string       $bodyContent
     * @param  array        $options  [action_url, action_text, recipient_name, user_id, trigger_type, template_id, created_by]
     * @return bool
     */
    public function sendDynamicEmail(
        string $toEmail,
        string $subject,
        string $bodyContent,
        array $options = []
    ): bool {
        $recipientName = $options['recipient_name'] ?? null;
        $triggerType   = $options['trigger_type'] ?? 'manual';
        $createdBy     = $options['created_by'] ?? 'system';

        try {
            Mail::to($toEmail, $recipientName)->send(
                new DynamicNotificationMail(
                    emailSubject: $subject,
                    emailBody: $bodyContent,
                    actionUrl: $options['action_url'] ?? null,
                    actionText: $options['action_text'] ?? null,
                    recipientName: $recipientName
                )
            );

            EmailLog::create([
                'user_id'         => $options['user_id'] ?? null,
                'recipient_email' => $toEmail,
                'recipient_name'  => $recipientName,
                'subject'         => $subject,
                'trigger_type'    => $triggerType,
                'template_id'     => $options['template_id'] ?? null,
                'reference_id'    => $options['reference_id'] ?? null,
                'reference_type'  => $options['reference_type'] ?? null,
                'status'          => 'sent',
                'payload'         => [
                    'body'       => $bodyContent,
                    'action_url' => $options['action_url'] ?? null,
                ],
                'sent_at'         => now(),
                'created_by'      => $createdBy,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("[EmailNotificationService] Gagal kirim email ke {$toEmail}: " . $e->getMessage());

            EmailLog::create([
                'user_id'         => $options['user_id'] ?? null,
                'recipient_email' => $toEmail,
                'recipient_name'  => $recipientName,
                'subject'         => $subject,
                'trigger_type'    => $triggerType,
                'template_id'     => $options['template_id'] ?? null,
                'status'          => 'failed',
                'error_message'   => $e->getMessage(),
                'created_by'      => $createdBy,
            ]);

            return false;
        }
    }

    /**
     * Kirim email berdasarkan kode template database (NotificationTemplate).
     *
     * @param  string  $templateCode
     * @param  string  $toEmail
     * @param  array   $variables  ['nama' => 'Budi', 'kode_booking' => 'BK-123']
     * @param  array   $options    [user_id, recipient_name, action_url, created_by]
     * @return bool
     */
    public function sendByTemplate(
        string $templateCode,
        string $toEmail,
        array $variables = [],
        array $options = []
    ): bool {
        $template = NotificationTemplate::where('code', $templateCode)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::warning("[EmailNotificationService] Template email '{$templateCode}' tidak ditemukan atau nonaktif.");
            return false;
        }

        $subject = $this->parsePlaceholders($template->title, $variables);
        $body    = $this->parsePlaceholders($template->body, $variables);

        return $this->sendDynamicEmail(
            toEmail: $toEmail,
            subject: $subject,
            bodyContent: $body,
            options: array_merge([
                'template_id'  => $template->id,
                'trigger_type' => $template->trigger_type ?: 'template',
                'action_url'   => $options['action_url'] ?? $template->action_url,
            ], $options)
        );
    }

    /**
     * Broadcast email ke semua pengguna aktif berdasarkan target role.
     *
     * @param  string  $targetRole  pasien | nakes | admin | all
     * @param  string  $subject
     * @param  string  $bodyContent
     * @param  array   $options     [action_url, action_text, created_by, template_id]
     * @return int     Jumlah email yang berhasil terkirim
     */
    public function broadcastEmail(
        string $targetRole,
        string $subject,
        string $bodyContent,
        array $options = []
    ): int {
        $recipients = $this->resolveRecipientsByRole($targetRole);
        $sentCount  = 0;

        foreach ($recipients as $recipient) {
            $parsedSubject = $this->parsePlaceholders($subject, ['nama' => $recipient['name']]);
            $parsedBody    = $this->parsePlaceholders($bodyContent, ['nama' => $recipient['name']]);

            $success = $this->sendDynamicEmail(
                toEmail: $recipient['email'],
                subject: $parsedSubject,
                bodyContent: $parsedBody,
                options: array_merge($options, [
                    'user_id'        => $recipient['user_id'],
                    'recipient_name' => $recipient['name'],
                    'trigger_type'   => 'broadcast',
                ])
            );

            if ($success) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Parse placeholder {variabel} dalam teks string.
     */
    public function parsePlaceholders(string $text, array $variables): string
    {
        if (empty($variables)) {
            return $text;
        }

        $search  = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[]  = '{' . $key . '}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Helper untuk mengambil daftar penerima email berdasarkan role.
     */
    protected function resolveRecipientsByRole(string $role): array
    {
        $recipients = [];

        if (in_array($role, ['pasien', 'all'])) {
            $pasiens = Users::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('roles.nama_role', 'pasien'))
                ->with('pasien')
                ->get();

            foreach ($pasiens as $u) {
                if ($u->email) {
                    $recipients[] = [
                        'user_id' => $u->id_user,
                        'email'   => $u->email,
                        'name'    => $u->pasien?->nama_lengkap ?? $u->email,
                        'role'    => 'pasien',
                    ];
                }
            }
        }

        if (in_array($role, ['nakes', 'all'])) {
            $nakes = Users::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('roles.nama_role', 'nakes'))
                ->with('tenagaMedis')
                ->get();

            foreach ($nakes as $u) {
                if ($u->email) {
                    $recipients[] = [
                        'user_id' => $u->id_user,
                        'email'   => $u->email,
                        'name'    => $u->tenagaMedis?->nama_lengkap ?? $u->email,
                        'role'    => 'nakes',
                    ];
                }
            }
        }

        if (in_array($role, ['admin', 'all'])) {
            $admins = Admin::where('is_active', true)->get();

            foreach ($admins as $adm) {
                if ($adm->email) {
                    $recipients[] = [
                        'user_id' => $adm->id_admin,
                        'email'   => $adm->email,
                        'name'    => $adm->nama_lengkap ?? $adm->email,
                        'role'    => 'admin',
                    ];
                }
            }
        }

        return $recipients;
    }
}
