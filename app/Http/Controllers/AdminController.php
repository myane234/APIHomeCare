<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Users;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * @group Kelola admin
 */

class AdminController extends Controller
{
    public function index()
    {
        $data = Admin::query()->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Data user Admin',
            'data' => $data,
            'total' => count($data)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
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
            $admin = DB::transaction(function () use ($validated) {
                // 1. Buat User
                $user = Users::create([
                    'email' => $validated['email'],
                    'password' => bcrypt($validated['password']),
                    'is_active' => true,
                ]);

                // 2. Buat Admin Detail
                $admin = Admin::create([
                    'id_user' => $user->id_user,
                    'nama_lengkap' => $validated['nama_lengkap'],
                    'tier_admin' => $validated['tier_admin'],
                ]);

                // 3. Set Role Global 'admin' ke user_roles
                $role = Role::firstOrCreate(['nama_role' => 'admin']);
                $user->roles()->sync([$role->nama_role]);

                return $admin;
            });

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Create Admin',
                'data' => $admin
            ], 201);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Create Admin'
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
            'tier_admin' => ['sometimes', 'required', 'string', 'exists:admin_tiers,nama_tier'],
        ]);

        if (isset($validated['tier_admin']) && in_array(strtolower($validated['tier_admin']), ['super admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah tier menjadi Super Admin.'
            ], 403);
        }

        $admin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Update Admin',
            'data' => $admin
        ], 200);
    }

    public function getTiers()
    {
        try {
            $tiers = DB::table('admin_tiers')
                ->whereNotIn(DB::raw('LOWER(nama_tier)'), ['super admin', 'super_admin'])
                ->pluck('nama_tier');

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengambil daftar tier',
                'data' => $tiers
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar tier'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $admin = Admin::findOrFail($id);
            $admin->delete();

            return response()->json([
                'success' => true,
                'message' => "Berhasil Hapus User"
            ], 200);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Hapus User'
            ], 500);
        }
    }
}