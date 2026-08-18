<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminTier;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function index()
    {
        // Ambil data admin langsung dari tabel admins
        $data = Admin::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Data Admin',
            'data' => $data,
            'total' => $data->count()
        ]);
    }

    public function store(Request $request)
    {
        // Validasi wajib unique:admins,email karena akun admin terpisah total
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'tier_admin' => ['required', 'string', 'exists:admin_tiers,nama_tier'],
        ]);

        if (in_array(strtolower($validated['tier_admin']), ['super admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menambahkan akun dengan tier Super Admin.'
            ], 403);
        }

        try {
            // Buat Record Admin secara langsung
            $admin = Admin::create([
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'nama_lengkap' => $validated['nama_lengkap'],
                'tier_admin' => $validated['tier_admin'],
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Membuat Admin Baru',
                'data' => $admin
            ], 201);

        } catch (Exception $e) {
            Log::error('Error Store Admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Membuat Admin'
            ], 500);
        }
    }

    public function show($id)
    {
        $admin = Admin::findOrFail($id);
        return response()->json($admin, 200);
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);

        $validated = $request->validate([
            'nama_lengkap' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:admins,email,' . $id . ',id_admin'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'tier_admin' => ['sometimes', 'required', 'string', 'exists:admin_tiers,nama_tier'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['tier_admin']) && in_array(strtolower($validated['tier_admin']), ['super admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah tier menjadi Super Admin.'
            ], 403);
        }

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $admin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Update Admin',
            'data' => $admin
        ], 200);
    }

    public function destroy($id)
    {
        try {
            $admin = Admin::findOrFail($id);
            $admin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Menghapus Akun Admin'
            ], 200);

        } catch (Exception $e) {
            Log::error('Error Destroy Admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Menghapus Admin'
            ], 500);
        }
    }

    public function getTiers()
    {
        $tiers = AdminTier::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Data Tier Admin',
            'data' => $tiers
        ]);
    }
}