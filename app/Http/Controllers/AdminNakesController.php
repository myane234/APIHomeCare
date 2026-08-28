<?php

namespace App\Http\Controllers;

use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Super Admin Nakes Management
 */
class AdminNakesController extends Controller
{
    /**
     * List semua pendaftaran nakes
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $status = strtolower((string) $request->query('status', 'pending'));

        $query = TenagaMedis::with(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
            ->orderBy('created_at', 'desc');

        if (in_array($status, ['pending', 'pelatihan', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $nakesRequests = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar pendaftaran Nakes.',
            'data' => $nakesRequests
        ], 200);
    }

    /**
     * List khusus tenaga medis yang sudah aktif / approved
     */
    public function listActiveNakes(Request $request)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::with(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar Nakes aktif.',
            'data' => $tenagaMedis
        ], 200);
    }

    /**
     * Detail lengkap nakes
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::with(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail Nakes.',
            'data' => $tenagaMedis
        ], 200);
    }

    /**
     * STEP 1: Ajukan Pendaftaran ke Tahap Pelatihan
     */
    public function setPelatihan(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::findOrFail($id);

        if ($tenagaMedis->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pendaftaran berstatus "pending" yang dapat diajukan ke tahap pelatihan.'
            ], 400);
        }

        $tenagaMedis->update([
            'status' => 'pelatihan',
            'admin_notes' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran Nakes berhasil diajukan ke tahap pelatihan.',
            'data' => $tenagaMedis->fresh(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
        ], 200);
    }

    /**
     * STEP 2: Approve Pelatihan & Aktifkan Akun
     */
    public function approve(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::findOrFail($id);

        if ($tenagaMedis->status !== 'pelatihan') {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran Nakes tidak bisa langsung di-approve. Harus diajukan ke tahap pelatihan terlebih dahulu.'
            ], 400);
        }

        $result = DB::transaction(function () use ($tenagaMedis) {
            $tenagaMedis->update([
                'status' => 'approved',
                'admin_notes' => null
            ]);

            $user = $tenagaMedis->user;
            if ($user && !$user->roles()->where('user_roles.nama_role', 'nakes')->exists()) {
                $user->roles()->attach('nakes');
            }

            return $tenagaMedis->fresh(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Nakes dinyatakan lulus pelatihan dan akun/role Nakes berhasil diaktifkan.',
            'data' => $result
        ], 200);
    }

    /**
     * REJECT: Tolak Pendaftaran
     */
    public function reject(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::findOrFail($id);

        if ($tenagaMedis->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Nakes yang sudah di-approve/aktif tidak bisa langsung di-reject.'
            ], 400);
        }

        if ($tenagaMedis->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran Nakes ini sudah di-reject sebelumnya.'
            ], 400);
        }

        $validate = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000']
        ], [
            'admin_notes.required' => 'Alasan penolakan (admin_notes) wajib diisi.'
        ]);

        $tenagaMedis->update([
            'status' => 'rejected',
            'admin_notes' => $validate['admin_notes']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran Nakes berhasil ditolak.',
            'data' => $tenagaMedis->fresh(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
        ], 200);
    }
}