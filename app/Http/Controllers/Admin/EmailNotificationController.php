<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\Users;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin\EmailNotificationController
 *
 * REST API untuk pengelolaan pengiriman email via Resend:
 *   - Kirim email custom ke email / user tertentu
 *   - Broadcast email promo / pengumuman ke seluruh pasien / nakes
 *   - Kirim ulang invoice booking selesai
 *   - Riwayat log pengiriman email
 */
class EmailNotificationController extends Controller
{
    public function __construct(protected EmailNotificationService $emailService)
    {
    }

    /**
     * POST /api/admin/emails/send
     *
     * Kirim email tunggal ke alamat email atau user tertentu.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'email'         => ['required_without:user_id', 'nullable', 'email'],
            'user_id'       => ['required_without:email', 'nullable', 'integer'],
            'subject'       => ['required', 'string', 'max:255'],
            'body'          => ['required', 'string'],
            'action_url'    => ['nullable', 'string', 'max:500'],
            'action_text'   => ['nullable', 'string', 'max:50'],
            'template_code' => ['nullable', 'string', 'exists:notification_templates,code'],
            'variables'     => ['nullable', 'array'],
        ]);

        $recipientEmail = $validated['email'] ?? null;
        $recipientName  = null;
        $userId         = $validated['user_id'] ?? null;

        if ($userId) {
            $user = Users::with(['pasien', 'tenagaMedis'])->find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User dengan ID tersebut tidak ditemukan',
                ], 404);
            }
            $recipientEmail = $user->email;
            $recipientName  = $user->pasien?->nama_lengkap ?? $user->tenagaMedis?->nama_lengkap ?? $user->email;
        }

        $subject = $validated['subject'];
        $body    = $validated['body'];

        if (!empty($validated['variables'])) {
            $subject = $this->emailService->parsePlaceholders($subject, $validated['variables']);
            $body    = $this->emailService->parsePlaceholders($body, $validated['variables']);
        }

        $adminEmail = $request->user()?->email ?? 'admin';

        $success = $this->emailService->sendDynamicEmail(
            toEmail: $recipientEmail,
            subject: $subject,
            bodyContent: $body,
            options: [
                'user_id'        => $userId,
                'recipient_name' => $recipientName,
                'action_url'     => $validated['action_url'] ?? null,
                'action_text'    => $validated['action_text'] ?? null,
                'trigger_type'   => 'manual',
                'created_by'     => $adminEmail,
            ]
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email. Silakan periksa log sistem / konfigurasi Resend.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Email berhasil dikirim ke {$recipientEmail}",
        ]);
    }

    /**
     * POST /api/admin/emails/broadcast
     *
     * Broadcast email promo / pengumuman ke seluruh user aktif berdasarkan target role.
     */
    public function broadcast(Request $request)
    {
        $validated = $request->validate([
            'target_role' => ['required', 'string', Rule::in(['pasien', 'nakes', 'admin', 'all'])],
            'subject'     => ['required', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'action_url'  => ['nullable', 'string', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:50'],
        ]);

        $adminEmail = $request->user()?->email ?? 'admin';

        $sentCount = $this->emailService->broadcastEmail(
            targetRole: $validated['target_role'],
            subject: $validated['subject'],
            bodyContent: $validated['body'],
            options: [
                'action_url'  => $validated['action_url'] ?? null,
                'action_text' => $validated['action_text'] ?? null,
                'created_by'  => $adminEmail,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Email broadcast berhasil dikirim ke {$sentCount} penerima ({$validated['target_role']})",
            'data'    => [
                'sent_count'  => $sentCount,
                'target_role' => $validated['target_role'],
            ],
        ]);
    }

    /**
     * POST /api/admin/emails/send-invoice/{id_booking}
     *
     * Kirim ulang invoice booking selesai ke email pasien.
     */
    public function sendInvoice(Request $request, int $id_booking)
    {
        $booking = Booking::findOrFail($id_booking);
        $adminEmail = $request->user()?->email ?? 'admin';

        $success = $this->emailService->sendInvoice($booking, createdBy: $adminEmail);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => "Gagal mengirim email invoice untuk booking #{$id_booking}. Pastikan data pasien dan email lengkap.",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Email invoice untuk booking #{$id_booking} berhasil dikirim",
        ]);
    }

    /**
     * GET /api/admin/emails/logs
     *
     * Daftar log riwayat pengiriman email dengan filter status, trigger_type, pencarian email.
     */
    public function logs(Request $request)
    {
        $query = EmailLog::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->input('trigger_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('recipient_email', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        [$data, $meta] = $this->paginateQuery($query, $request);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil log pengiriman email',
            'data'    => $data,
            'meta'    => $meta,
        ]);
    }
}
