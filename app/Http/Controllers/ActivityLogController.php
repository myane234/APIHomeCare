<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Tampilkan daftar Aktivitas Log (Admin only).
     *
     * Mendukung:
     * - Paginasi: `per_page=15` (default 15). Kirim `per_page=all` untuk semua data.
     * - Filter user_type: `user_type=Admin` (Admin, Pasien, Tenaga Medis, System).
     * - Filter action: `action=CREATE_PROMO`.
     * - Pencarian: `search` / `q` (mencari di action, description, user_name).
     * - Filter Tanggal: `start_date` (YYYY-MM-DD) dan `end_date` (YYYY-MM-DD).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        // Filter User Type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter Action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Pencarian (Search)
        if ($request->filled('search') || $request->filled('q')) {
            $search = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('user_name', 'like', '%' . $search . '%');
            });
        }

        // Filter Rentang Tanggal
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Pengurutan (Default paling baru)
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage === 'all') {
            $data = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 15;
            $data = $query->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar log aktivitas',
            'data'    => $data,
        ], 200);
    }

    /**
     * Tambah catatan log aktivitas manual.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'action'      => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'user_type'   => ['nullable', 'string', 'max:50'],
        ]);

        $log = ActivityLog::log(
            $validated['action'],
            $validated['description'] ?? null,
            $request->user(),
            $validated['user_type'] ?? 'Admin'
        );

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mencatat log aktivitas',
            'data'    => $log
        ], 201);
    }

    /**
     * Detail 1 log aktivitas.
     */
    public function show($id)
    {
        $log = ActivityLog::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail log aktivitas',
            'data'    => $log
        ], 200);
    }

    /**
     * Hapus 1 log aktivitas.
     */
    public function destroy($id)
    {
        $log = ActivityLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menghapus log aktivitas'
        ], 200);
    }

    /**
     * Bersihkan log aktivitas lama (Opsional).
     */
    public function clear(Request $request)
    {
        $request->validate([
            'days' => ['nullable', 'integer', 'min:1'],
        ]);

        $days = $request->input('days', 30); // Default hapus log lebih tua dari 30 hari
        $cutoffDate = now()->subDays($days);

        $deletedCount = ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        ActivityLog::log('CLEAR_ACTIVITY_LOGS', "Membersihkan {$deletedCount} log yang lebih tua dari {$days} hari", $request->user());

        return response()->json([
            'success' => true,
            'message' => "Berhasil membersihkan {$deletedCount} log aktivitas lebih tua dari {$days} hari"
        ], 200);
    }
}
