<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Users;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

/**
 * @group Super Admin Authentication
 * Endpoint autentikasi untuk Super Admin
 */
class SuperAdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $validate = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $validate['email'])->first();

        if (!$admin || !Hash::check($validate['password'], $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan.',
            ], 403);
        }

        // Harus berstatus Super Admin
        if ($admin->tier_admin !== 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang diizinkan.',
            ], 403);
        }

        // Revoke token lama (opsional, bersihkan sesi sebelumnya)
        $admin->tokens()->where('name', 'super-admin-token')->delete();

        $token = $admin->createToken('super-admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Berhasil login sebagai Super Admin',
            'data'    => [
                'token'      => $token,
                'roles'      => ['admin', 'superadmin'],
                'tier_admin' => $admin->tier_admin,
                'nama'       => $admin->nama_lengkap,
                'email'      => $admin->email,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout',
        ]);
    }

    public function me(Request $request)
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'email'      => $admin->email,
                'nama'       => $admin->nama_lengkap,
                'tier_admin' => $admin->tier_admin,
                'roles'      => ['admin', strtolower(str_replace(' ', '', $admin->tier_admin))],
            ],
        ]);
    }
}
