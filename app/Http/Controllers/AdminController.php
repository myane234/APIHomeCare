<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminTier;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Ambil Data profile admin
     */
    public function me(Request $request)
{
    return response()->json([
        'success' => true,
        'message' => 'Berhasil mengambil data akun admin',
        'data'    => $request->user()
    ], 200);
}

/**
 * Ambil semua data Akun Admins
 */
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

    /**
     * Buat 1 Data akun admin 
     */
    public function store(Request $request)
{
    $validated = $request->validate([
        'email'        => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
        'password'     => ['required', 'string', 'min:8'],
        'nama_lengkap' => ['required', 'string', 'max:255'],
        'tier_admin'   => ['required', 'string', 'exists:admin_tiers,nama_tier'],
        'deskripsi'    => ['nullable', 'string'],
        'foto_profile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,ico', 'max:2048']
    ]);

    if (in_array(strtolower($validated['tier_admin']), ['super admin', 'super_admin'])) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak dapat menambahkan akun dengan tier Super Admin.'
        ], 403);
    }

    try {
        $fotopath = null;
        
        if ($request->hasFile('foto_profile')) {
            $fotopath = $request->file('foto_profile')->store('foto_admin', 'public');
        }

        // Buat Record Admin secara langsung
        $admin = Admin::create([
            'email'        => $validated['email'],
            'password'     => bcrypt($validated['password']),
            'nama_lengkap' => $validated['nama_lengkap'],
            'deskripsi'    => $validated['deskripsi'] ?? null, 
            'foto_profile' => $fotopath,
            'tier_admin'   => $validated['tier_admin'],
            'is_active'    => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Membuat Admin Baru',
            'data'    => $admin
        ], 201);

    } catch (Exception $e) {
        Log::error('Error Store Admin: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal Membuat Admin'
        ], 500);
    }
}

/**
 * AMbil 1 data akun admin pake id
 */
    public function show($id)
    {
        $admin = Admin::findOrFail($id);
        return response()->json($admin, 200);
    }

    

    /**
     * Updte data akun admin, menggunakan ID
     */
  public function update(Request $request, $id)
{
    $admin = Admin::findOrFail($id);

    $validated = $request->validate([
        'nama_lengkap' => ['sometimes', 'required', 'string', 'max:255'],
        'email'        => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:admins,email,' . $id . ',id_admin'],
        'password'     => ['sometimes', 'required', 'string', 'min:8'],
        'tier_admin'   => ['sometimes', 'required', 'string', 'exists:admin_tiers,nama_tier'],
        'is_active'    => ['sometimes', 'boolean'],
        'deskripsi'    => ['nullable', 'string'],
        'foto_profile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,ico', 'max:2048']
    ]);

    if (isset($validated['tier_admin']) && in_array(strtolower($validated['tier_admin']), ['super admin', 'super_admin'])) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak dapat mengubah tier menjadi Super Admin.'
        ], 403);
    }

    if ($request->hasFile('foto_profile')) {
        if ($admin->foto_profile && Storage::disk('public')->exists($admin->foto_profile)) {
            Storage::disk('public')->delete($admin->foto_profile);
        }


        $validated['foto_profile'] = $request->file('foto_profile')->store('foto_admin', 'public');
    }

    if (isset($validated['password'])) {
        $validated['password'] = bcrypt($validated['password']);
    }

   
    $admin->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Berhasil Update Admin',
        'data'    => $admin
    ], 200);
}

/**
 * Hapus Akun admin pake ID
 */
    public function destroy($id)
    {
        try {
            $admin = Admin::findOrFail($id);

            // Perbaikan: Hapus file foto dari storage saat data admin dihapus
            if ($admin->foto_profile && Storage::disk('public')->exists($admin->foto_profile)) {
                Storage::disk('public')->delete($admin->foto_profile);
            }

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

    /**
     * Hapus ganti akun admin sendiri
     */
    public function changePassword(Request $request, $id)
{
   
    $admin = $request->user(); 

    $validated = $request->validate([
        'password_lama' => ['required', 'string'],
        'password_baru' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    if (!Hash::check($validated['password_lama'], $admin->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Password lama salah.'
        ], 400);
    }

    $admin->update([
        'password' => bcrypt($validated['password_baru'])
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Password Anda berhasil diperbarui.'
    ]);
}

/**
 * Update profiles 1 admin
 */
public function updateProfile(Request $request)
{
  
    $admin = $request->user(); 

    $validated = $request->validate([
        'nama_lengkap' => ['sometimes', 'required', 'string', 'max:255'],
        'email'        => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:admins,email,' . $admin->id_admin . ',id_admin'],
        'deskripsi'    => ['nullable', 'string'],
        'foto_profile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,ico', 'max:2048']
    ]);

    if ($request->hasFile('foto_profile')) {
        if ($admin->foto_profile && Storage::disk('public')->exists($admin->foto_profile)) {
            Storage::disk('public')->delete($admin->foto_profile);
        }

        $validated['foto_profile'] = $request->file('foto_profile')->store('foto_admin', 'public');
    }

    
    $admin->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Berhasil memperbarui profile',
        'data'    => $admin
    ], 200);
}

}

