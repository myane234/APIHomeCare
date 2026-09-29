<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\Users;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin\NotificationTemplateController
 *
 * CRUD template notifikasi + fitur broadcast manual ke semua user berdasarkan target_role.
 */
class NotificationTemplateController extends Controller
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    // ─── CRUD Template ────────────────────────────────────────────────────────

    /**
     * GET /admin/notification-templates
     * Daftar semua template dengan filter opsional.
     */
    public function index(Request $request)
    {
        $query = NotificationTemplate::query()->with('schedules');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('target_role')) {
            $query->where('target_role', $request->input('target_role'));
        }

        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->input('trigger_type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        $query->orderByDesc('created_at');

        [$data, $meta] = $this->paginateQuery($query, $request);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar template notifikasi',
            'data'    => $data,
            'meta'    => $meta,
        ]);
    }

    /**
     * POST /admin/notification-templates
     * Buat template baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'         => ['required', 'string', 'max:100', 'unique:notification_templates,code'],
            'name'         => ['required', 'string', 'max:255'],
            'target_role'  => ['nullable', 'string', Rule::in(['pasien', 'nakes', 'admin', 'all'])],
            'trigger_type' => ['nullable', 'string', 'max:100'],
            'title'        => ['required', 'string', 'max:255'],
            'body'         => ['required', 'string'],
            'action_url'   => ['nullable', 'string', 'max:500'],
            'channel'      => ['nullable', 'string', 'max:50'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $validated['created_by'] = $request->user()?->email ?? 'admin';
        $validated['updated_by'] = $request->user()?->email ?? 'admin';

        $template = NotificationTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil dibuat',
            'data'    => $template,
        ], 201);
    }

    /**
     * GET /admin/notification-templates/{id}
     * Detail template (support pencarian by ID atau code).
     */
    public function show(string $id)
    {
        $template = NotificationTemplate::with('schedules')
            ->where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail template notifikasi',
            'data'    => $template,
        ]);
    }

    /**
     * PUT /admin/notification-templates/{id}
     * Update template.
     */
    public function update(Request $request, string $id)
    {
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'code'         => ['sometimes', 'required', 'string', 'max:100',
                               Rule::unique('notification_templates', 'code')->ignore($template->id)],
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'target_role'  => ['nullable', 'string', Rule::in(['pasien', 'nakes', 'admin', 'all'])],
            'trigger_type' => ['nullable', 'string', 'max:100'],
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'body'         => ['sometimes', 'required', 'string'],
            'action_url'   => ['nullable', 'string', 'max:500'],
            'channel'      => ['nullable', 'string', 'max:50'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $validated['updated_by'] = $request->user()?->email ?? 'admin';

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil diupdate',
            'data'    => $template->fresh('schedules'),
        ]);
    }

    /**
     * DELETE /admin/notification-templates/{id}
     * Hapus template (soft delete via AuditableSoftDeletes).
     */
    public function destroy(Request $request, string $id)
    {
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();

        $template->deleted_by = $request->user()?->email ?? 'admin';
        $template->save();
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil dihapus',
        ]);
    }

    // ─── Schedule Management ──────────────────────────────────────────────────

    /**
     * POST /admin/notification-templates/{id}/schedules
     * Tambah jadwal ke template.
     */
    public function storeSchedule(Request $request, int $templateId)
    {
        $template = NotificationTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'cron_expression' => ['nullable', 'string', 'max:100'],
            'delay_unit'      => ['nullable', 'string', Rule::in(['minutes', 'hours', 'days'])],
            'delay_value'     => ['nullable', 'integer', 'min:1'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $validated['template_id'] = $template->id;
        $validated['created_by']  = $request->user()?->email ?? 'admin';
        $validated['updated_by']  = $request->user()?->email ?? 'admin';

        $schedule = NotificationSchedule::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal notifikasi berhasil ditambahkan',
            'data'    => $schedule,
        ], 201);
    }

    /**
     * DELETE /admin/notification-templates/{id}/schedules/{scheduleId}
     * Hapus jadwal.
     */
    public function destroySchedule(Request $request, int $templateId, int $scheduleId)
    {
        $schedule = NotificationSchedule::where('template_id', $templateId)
            ->where('id', $scheduleId)
            ->firstOrFail();

        $schedule->deleted_by = $request->user()?->email ?? 'admin';
        $schedule->save();
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal notifikasi berhasil dihapus',
        ]);
    }

    // ─── Broadcast Manual ─────────────────────────────────────────────────────

    /**
     * POST /admin/notification-templates/{id}/broadcast
     *
     * Kirim notifikasi secara broadcast ke semua user berdasarkan target_role template,
     * atau ke user_ids tertentu yang dikirim dalam request body.
     *
     * Request body (optional):
     * {
     *   "user_ids":   [1, 2, 3],       // jika ingin kirim ke user tertentu saja
     *   "variables":  {"nama": "Budi"}, // placeholder override
     *   "action_url": "https://...",   // override action_url template
     *   "data":       {}               // metadata JSON tambahan
     * }
     */
    public function broadcast(Request $request, string $id)
    {
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->where('is_active', true)
            ->firstOrFail();

        $request->validate([
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'variables'  => ['nullable', 'array'],
            'action_url' => ['nullable', 'string', 'max:500'],
            'data'       => ['nullable', 'array'],
        ]);

        $variables  = $request->input('variables', []);
        $actionUrl  = $request->input('action_url', $template->action_url);
        $createdBy  = $request->user()?->email ?? 'admin';

        $title = $this->notificationService->parsePlaceholders($template->title, $variables);
        $body  = $this->notificationService->parsePlaceholders($template->body, $variables);

        // Kirim ke user_ids spesifik
        if ($request->filled('user_ids')) {
            $recipients = array_map(fn ($uid) => [
                'user_id'   => $uid,
                'user_role' => $template->target_role ?? 'pasien',
            ], $request->input('user_ids'));

            $count = $this->notificationService->sendBulk($recipients, $title, $body, [
                'template_id' => $template->id,
                'action_url'  => $actionUrl,
                'data'        => $request->input('data'),
                'created_by'  => $createdBy,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Notifikasi berhasil dikirim ke {$count} user",
                'data'    => ['count' => $count],
            ]);
        }

        // Broadcast ke semua user berdasarkan target_role template
        $targetRole = $template->target_role ?? 'all';
        $recipients = $this->resolveRecipientsByRole($targetRole);

        if (empty($recipients)) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada penerima yang ditemukan untuk role: ' . $targetRole,
                'data'    => ['count' => 0],
            ]);
        }

        $count = $this->notificationService->sendBulk($recipients, $title, $body, [
            'template_id' => $template->id,
            'action_url'  => $actionUrl,
            'data'        => $request->input('data'),
            'created_by'  => $createdBy,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Notifikasi berhasil di-broadcast ke {$count} user ({$targetRole})",
            'data'    => ['count' => $count, 'target_role' => $targetRole],
        ]);
    }

    // ─── Stats ────────────────────────────────────────────────────────────────

    /**
     * GET /admin/notification-templates/{id}/stats
     * Statistik pengiriman template.
     */
    public function stats(string $id)
    {
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();

        $totalSent   = Notification::where('template_id', $template->id)->count();
        $totalRead   = Notification::where('template_id', $template->id)->where('is_read', true)->count();
        $totalUnread = $totalSent - $totalRead;
        $readRate    = $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'message' => 'Statistik template notifikasi',
            'data'    => [
                'template_id'  => $template->id,
                'template_code'=> $template->code,
                'total_sent'   => $totalSent,
                'total_read'   => $totalRead,
                'total_unread' => $totalUnread,
                'read_rate'    => $readRate,
            ],
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Resolve daftar penerima berdasarkan role.
     *
     * @return array  Array of ['user_id' => int, 'user_role' => string]
     */
    private function resolveRecipientsByRole(string $role): array
    {
        $recipients = [];

        if (in_array($role, ['pasien', 'all'])) {
            $pasienUserIds = Users::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('roles.nama_role', 'pasien'))
                ->pluck('id_user');

            foreach ($pasienUserIds as $userId) {
                $recipients[] = ['user_id' => $userId, 'user_role' => 'pasien'];
            }
        }

        if (in_array($role, ['nakes', 'all'])) {
            $nakesUserIds = Users::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('roles.nama_role', 'nakes'))
                ->pluck('id_user');

            foreach ($nakesUserIds as $userId) {
                $recipients[] = ['user_id' => $userId, 'user_role' => 'nakes'];
            }
        }

        if (in_array($role, ['admin', 'all'])) {
            $adminIds = Admin::where('is_active', true)
                ->pluck('id_admin');

            foreach ($adminIds as $adminId) {
                $recipients[] = ['user_id' => $adminId, 'user_role' => 'admin'];
            }
        }

        return $recipients;
    }
}
