<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Api\NotificationController
 *
 * Endpoint untuk user melihat dan mengelola inbox notifikasi mereka.
 *
 * GET  /api/notifications          - Inbox paginated (filter unread_only)
 * GET  /api/notifications/unread-count - Jumlah notifikasi belum dibaca
 * PATCH /api/notifications/{id}/read  - Tandai satu notifikasi sebagai dibaca
 * PATCH /api/notifications/read-all   - Tandai semua notifikasi sebagai dibaca
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     *
     * Inbox notifikasi user yang sedang login, urut terbaru,
     * paginated. Mendukung filter unread_only.
     *
     * Query params:
     *   - unread_only: boolean (default: false)
     *   - per_page   : int (default: 15, max: 100)
     *   - page       : int
     */
    public function index(Request $request)
    {
        [$userId, $userRole] = $this->resolveUser($request);

        $query = Notification::where('user_id', $userId)
            ->where('user_role', $userRole)
            ->orderByDesc('created_at');

        // Filter hanya yang belum dibaca
        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        [$data, $meta] = $this->paginateQuery($query, $request);

        // Jumlah unread untuk badge
        $unreadCount = Notification::where('user_id', $userId)
            ->where('user_role', $userRole)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success'      => true,
            'message'      => 'Berhasil mengambil inbox notifikasi',
            'data'         => $data,
            'unread_count' => $unreadCount,
            'meta'         => $meta,
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     *
     * Kembalikan hanya jumlah notifikasi yang belum dibaca.
     * Berguna untuk badge tanpa perlu load seluruh list.
     */
    public function unreadCount(Request $request)
    {
        [$userId, $userRole] = $this->resolveUser($request);

        $count = Notification::where('user_id', $userId)
            ->where('user_role', $userRole)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil jumlah notifikasi belum dibaca',
            'data'    => ['unread_count' => $count],
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     *
     * Tandai satu notifikasi sebagai sudah dibaca.
     * User hanya bisa menandai notifikasi miliknya sendiri.
     */
    public function markAsRead(Request $request, int $id)
    {
        [$userId, $userRole] = $this->resolveUser($request);

        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->where('user_role', $userRole)
            ->firstOrFail();

        if (!$notification->is_read) {
            $notification->update([
                'is_read'    => true,
                'read_at'    => now(),
                'updated_by' => (string) $userId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil ditandai sebagai dibaca',
            'data'    => $notification,
        ]);
    }

    /**
     * PATCH /api/notifications/read-all
     *
     * Tandai semua notifikasi milik user yang sedang login sebagai dibaca.
     */
    public function markAllAsRead(Request $request)
    {
        [$userId, $userRole] = $this->resolveUser($request);

        $count = Notification::where('user_id', $userId)
            ->where('user_role', $userRole)
            ->where('is_read', false)
            ->count();

        if ($count > 0) {
            Notification::where('user_id', $userId)
                ->where('user_role', $userRole)
                ->where('is_read', false)
                ->update([
                    'is_read'    => true,
                    'read_at'    => now(),
                    'updated_by' => (string) $userId,
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} notifikasi berhasil ditandai sebagai dibaca",
            'data'    => ['marked_count' => $count],
        ]);
    }

    /**
     * DELETE /api/notifications/{id}
     *
     * Hapus satu notifikasi milik user. (opsional, jika dibutuhkan)
     */
    public function destroy(Request $request, int $id)
    {
        [$userId, $userRole] = $this->resolveUser($request);

        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->where('user_role', $userRole)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dihapus',
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Resolve user_id dan user_role dari user yang sedang login.
     *
     * Mendukung multi-guard: Users (pasien/nakes), Admin.
     *
     * @return array  [int $userId, string $userRole]
     */
    private function resolveUser(Request $request): array
    {
        $user = $request->user();

        // Guard Admin
        if ($user instanceof \App\Models\Admin) {
            return [$user->id_admin, 'admin'];
        }

        // Guard Users (pasien atau nakes ditentukan dari role di DB)
        /** @var \App\Models\Users $user */
        $roles = $user->roles()->pluck('roles.nama_role')->toArray();

        if (in_array('nakes', $roles)) {
            $role = 'nakes';
        } else {
            $role = 'pasien';
        }

        return [$user->id_user, $role];
    }
}
