<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Users;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

/**
 * @group Auth untuk Admin
 */

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $validate = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = Users::whereEmail($validate['email'])->first();

        if (!$user || !Hash::check($validate['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Data login salah'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan.'
            ], 403);
        }

        // 3. Cek Apakah Memang Admin
        $admin = Admin::where('id_user', $user->id_user)->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses admin'
            ], 403);
        }

        // 4. Generate Token & Response Data
        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Login sebagai Admin',
            'data' => [
                'token' => $token,
                'nama' => $admin->nama_lengkap,
                'tier_admin' => $admin->tier_admin,
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Logout'
        ], 200);
    }
}