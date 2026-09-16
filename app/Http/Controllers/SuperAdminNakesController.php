<?php

namespace App\Http\Controllers;

use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuperAdminNakesController extends Controller
{
    public function index(Request $request)
    {
        
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $query = TenagaMedis::with(['user', 'pasien', 'kategoriLayanan'])
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('no_str', 'like', "%{$search}%")
                  ->orWhere('jenis_tenaga_medis', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%");
                  });
            });
        }

        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data Nakes',
            'data'    => $data,
        ]);
    }

    public function show(Request $request, $id)
    {
        $tenagaMedis = TenagaMedis::with(['user', 'pasien', 'kategoriLayanan'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail data Nakes berhasil diambil',
            'data'    => $tenagaMedis,
        ]);
    }

    public function update(Request $request, $id)
    {
        $tenagaMedis = TenagaMedis::findOrFail($id);

        $validated = $request->validate([
            'nama_lengkap'       => ['sometimes', 'required', 'string', 'max:255'],
            'nik'                => ['sometimes', 'required', 'string', 'regex:/^[0-9]{16}$/'],
            'jenis_tenaga_medis' => ['sometimes', 'required', 'string', 'max:100'],
            'no_str'             => ['sometimes', 'required', 'string', 'regex:/^[0-9]{16}$/'],
            'lulusan'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude'           => ['sometimes', 'nullable', 'numeric'],
            'longitude'          => ['sometimes', 'nullable', 'numeric'],
            'foto_profile'       => ['sometimes', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'id_wilayah_layanan' => ['sometimes', 'nullable', 'exists:wilayah_layanan,id'],
            'alamat_lengkap'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'kategori_layanan'   => ['sometimes', 'array'],
            'kategori_layanan.*' => ['exists:kategori_layanans,id_kategori_layanan'],
        ]);

        // Auto-geocode address if updated and coordinates are not explicitly passed
        if ($request->has('alamat_lengkap') && !$request->filled('latitude')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'smartHomeCare/1.0'
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'q'      => $request->input('alamat_lengkap'),
                    'format' => 'json',
                    'limit'  => 1
                ]);

                if ($response->successful() && !empty($response->json())) {
                    $geoData = $response->json()[0];
                    $validated['latitude'] = (float) $geoData['lat'];
                    $validated['longitude'] = (float) $geoData['lon'];
                }
            } catch (\Throwable $e) {
                // Fail silently
            }
        }

        if ($request->hasFile('foto_profile')) {
            $file = $request->file('foto_profile');
            $filename = 'nakes_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads/nakes', $filename, 'public');
            $validated['foto_profile'] = '/storage/' . $path;
        }

        $tenagaMedis->fill($validated);
        $tenagaMedis->save();

        if ($request->has('kategori_layanan')) {
            $tenagaMedis->kategoriLayanan()->sync($request->kategori_layanan);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data Nakes berhasil diperbarui oleh Super Admin',
            'data'    => $tenagaMedis->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenagaMedis = TenagaMedis::findOrFail($id);
        $user = $tenagaMedis->user;

        if ($user) {
            $user->roles()->detach(3);
        }

        $tenagaMedis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Nakes berhasil dihapus oleh Super Admin',
        ]);
    }
}