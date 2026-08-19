<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminTier;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

/**
 * @authenticated
 */
class AdminController extends Controller
{
    /**
     * Get Profile Saya
     * 
     * Mengambil data profil admin yang sedang login / terautentikasi.
     * 
     * @group CMS Admin
     * @subgroup Profil
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
     * Update Profile Saya
     * 
     * Memperbarui profil akun admin yang sedang login.
     * 
     * @group CMS Admin
     * @subgroup Profil
     * 
     * @bodyParam nama_lengkap string Nama lengkap admin. Example: John Doe
     * @bodyParam email string email Email admin (harus unik). Example: john@example.com
     * @bodyParam deskripsi string Deskripsi/bio admin. Example: Admin Operasional
     * @bodyParam foto_profile file File foto profil (jpeg, png, jpg, webp, ico - max 2MB).
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

    /**
     * Ubah Password Saya
     * 
     * Mengubah password akun admin yang sedang login.
     * 
     * @group CMS Admin
     * @subgroup Profil
     * 
     * @urlParam id required ID Admin yang sedang login. Example: 1
     * @bodyParam password_lama string required Password lama pengguna saat ini. Example: secret123
     * @bodyParam password_baru string required Password baru minimal 8 karakter. Example: newsecret123
     * @bodyParam password_baru_confirmation string required Konfirmasi password baru. Example: newsecret123
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Password Anda berhasil diperbarui."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Password lama salah."
     * }
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
     * Daftar Semua Admin
     * 
     * Mengambil seluruh data akun admin yang terdaftar.
     * 
     * @group CMS Admin
     * @subgroup Kelola Admin
     */
    public function index()
    {
        $data = Admin::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Data Admin',
            'data'    => $data,
            'total'   => $data->count()
        ]);
    }

    /**
     * Buat Admin Baru
     * 
     * Membuat data akun admin baru. Catatan: Tidak diperbolehkan membuat admin dengan tier "Super Admin".
     * 
     * @group CMS Admin
     * @subgroup Kelola Admin
     * 
     * @bodyParam email string required Email unik admin. Example: admin2@example.com
     * @bodyParam password string required Password minimal 8 karakter. Example: password123
     * @bodyParam nama_lengkap string required Nama lengkap admin. Example: Jane Doe
     * @bodyParam tier_admin string required Nama tier admin yang terdaftar di `admin_tiers`. Example: Admin Content
     * @bodyParam deskripsi string Deskripsi opsional. Example: Penanggung jawab konten
     * @bodyParam foto_profile file Gambar profil (jpeg, png, jpg, webp, ico - max 2MB).
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Berhasil Membuat Admin Baru",
     *   "data": {
     *     "id_admin": 2,
     *     "email": "admin2@example.com",
     *     "nama_lengkap": "Jane Doe",
     *     "tier_admin": "Admin Content",
     *     "is_active": true
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Tidak dapat menambahkan akun dengan tier Super Admin."
     * }
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
     * Detail Admin
     * 
     * Mengambil detail 1 data admin berdasarkan ID.
     * 
     * @group CMS Admin
     * @subgroup Kelola Admin
     * 
     * @urlParam id required ID dari Admin. Example: 1
     */
    public function show($id)
    {
        $admin = Admin::findOrFail($id);
        return response()->json($admin, 200);
    }

    /**
     * Update Data Admin
     * 
     * Memperbarui data spesifik admin berdasarkan ID.
     * 
     * @group CMS Admin
     * @subgroup Kelola Admin
     * 
     * @urlParam id required ID Admin yang akan diubah. Example: 1
     * @bodyParam nama_lengkap string Nama lengkap admin. Example: John Doe Updated
     * @bodyParam email string Email admin. Example: john_new@example.com
     * @bodyParam password string Password baru (minimal 8 karakter). Example: newpassword123
     * @bodyParam tier_admin string Nama tier admin (tidak boleh diubah ke Super Admin). Example: Admin Operasional
     * @bodyParam is_active boolean Status aktif admin. Example: true
     * @bodyParam deskripsi string Deskripsi opsional.
     * @bodyParam foto_profile file File foto profil baru.
     * 
     * @response 403 {
     *   "success": false,
     *   "message": "Tidak dapat mengubah tier menjadi Super Admin."
     * }
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
     * Hapus Admin
     * 
     * Menghapus data akun admin beserta berkas foto profilnya dari storage.
     * 
     * @group CMS Admin
     * @subgroup Kelola Admin
     * 
     * @urlParam id required ID Admin yang akan dihapus. Example: 1
     */
    public function destroy($id)
    {
        try {
            $admin = Admin::findOrFail($id);

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

    /**
     * Daftar Tier Admin
     * 
     * Mengambil seluruh data referensi Tier Admin yang tersedia.
     * 
     * @group CMS Admin
     * @subgroup Kelola Admin
     */
    public function getTiers()
    {
        $tiers = AdminTier::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Data Tier Admin',
            'data'    => $tiers
        ]);
    }
}