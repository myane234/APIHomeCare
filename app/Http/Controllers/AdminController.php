<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\KategoriAdmin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Users;
use App\Models\Role;
/**
 * @group Super Admin - Admin Management
 *
 * APIs for Super Admin to create, list, and delete CMS Admin users
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
            'tier_admin' => ['required', 'string', 'max:255'],
        ]);

        if (strtolower($validated['tier_admin']) === 'super admin' || strtolower($validated['tier_admin']) === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menambahkan akun dengan tier Super Admin.'
            ], 403);
        }

        try {
            $admin = DB::transaction(function () use ($validated) {
                $user = Users::create([
                    'email' => $validated['email'],
                    'password' => bcrypt($validated['password']),
                    'is_active' => true,
                ]);

                $admin = Admin::create([
                    'id_user' => $user->id_user,
                    'nama_lengkap' => $validated['nama_lengkap'],
                    'tier_admin' => $validated['tier_admin'],
                ]);

                $roleName = strtolower($validated['tier_admin']);
                $role = Role::firstOrCreate(['nama_role' => $roleName]);
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
                'message' => 'Gagal Create Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $admin = Admin::query()->findOrFail($id);
        return response()->json($admin, 200);
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::query()->findOrFail($id);

        $validated = $request->validate([
            'nama_lengkap' => ['sometimes', 'required', 'string', 'max:255'],
            'tier_admin' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        if (isset($validated['tier_admin']) && (strtolower($validated['tier_admin']) === 'super admin' || strtolower($validated['tier_admin']) === 'super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah tier menjadi Super Admin.'
            ], 403);
        }

        $admin->fill($validated);
        $admin->save();

        if (isset($validated['tier_admin'])) {
            $user = $admin->user;
            if ($user) {
                $roleName = strtolower($validated['tier_admin']);
                $role = Role::firstOrCreate(['nama_role' => $roleName]);
                $user->roles()->sync([$role->nama_role]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Update Admin',
            'data' => $admin
        ], 200);
    }

    public function getTiers()
    {
        try {
            $roles = Role::whereNotIn(DB::raw('LOWER(nama_role)'), ['super admin', 'super_admin'])
                ->pluck('nama_role');

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengambil daftar tier',
                'data' => $roles
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
        $admin = Admin::query()->findOrFail($id);
        $admin->delete($id);

        return response()->json([
            'success' => true,
            'message' => "Berhasil Hapus User"
        ], 200);

    } catch(Exception $e) {
        Log::error($e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal Hapus User'
        ], 500);
    }
        
    }
}

