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

        $admin = Admin::where('email', $validate['email'])->first();

        if (!$admin || !Hash::check($validate['password'], $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Data login salah'
            ], 401);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan.'
            ], 403);
        }

        // Generate Token & Response Data
        $token = $admin->createToken('admin-token')->plainTextToken;

        // Fetch Tier details (permissions & slug)
        $tier = \App\Models\AdminTier::where('nama_tier', $admin->tier_admin)
            ->orWhere('slug', \Illuminate\Support\Str::slug($admin->tier_admin))
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Login sebagai Admin',
            'data' => [
                'token' => $token,
                'nama' => $admin->nama_lengkap,
                'tier_admin' => $admin->tier_admin,
                'tier_slug' => $tier ? $tier->slug : \Illuminate\Support\Str::slug($admin->tier_admin),
                'permissions' => $tier ? ($tier->permissions ?? []) : [],
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