<?php

namespace App\Http\Controllers;

use App\Models\Kecamatan;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * @group API Master Kecamatan
 */
class KecamatanController extends Controller
{
    public function index(Request $request)
    {
        $perPage    = (int) $request->query('per_page', 50);
        $withKelurahan = $request->boolean('include_kelurahans', false);

        // Selalu load kotaKabupaten agar nama_regency tersedia
        $relations = $withKelurahan
            ? ['kelurahans', 'kotaKabupaten']
            : ['kotaKabupaten'];

        $query = Kecamatan::with($relations);

        if ($request->has('regency_id')) {
            $query->where('regency_id', $request->query('regency_id'));
            // Otomatis sertakan kelurahans bila filter per kota
            if (!$withKelurahan) {
                $query->with('kelurahans');
            }
        }

        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar Kecamatan',
            'data'    => $data->items(),
            'meta'    => [
                'total'        => $data->total(),
                'per_page'     => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kecamatan' => ['required', 'string', 'unique:master_kecamatan,id_kecamatan'],
            // Validasi foreign key ke master_kota_kabupaten.id_kota
            'regency_id' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'nama_kecamatan' => ['required', 'string', 'max:255'],
        ]);

        try {
            $kecamatan = Kecamatan::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Berhasil menambahkan Kecamatan',
                'data' => $kecamatan->load(['kelurahans', 'kotaKabupaten'])
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan Kecamatan'
            ], 500);
        }
    }

    public function show($id)
    {
        // Load relasi kelurahans dan kotaKabupaten sekaligus
        $kecamatan = Kecamatan::with(['kelurahans', 'kotaKabupaten'])->find($id);

        if (!$kecamatan) {
            return response()->json([
                'success' => false,
                'message' => 'Kecamatan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data Kecamatan',
            'data' => $kecamatan
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $kecamatan = Kecamatan::find($id);

        if (!$kecamatan) {
            return response()->json([
                'success' => false,
                'message' => 'Kecamatan tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'regency_id' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'nama_kecamatan' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        try {
            $kecamatan->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil memperbarui Kecamatan',
                'data' => $kecamatan->load(['kelurahans', 'kotaKabupaten'])
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui Kecamatan'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $kecamatan = Kecamatan::find($id);

        if (!$kecamatan) {
            return response()->json([
                'success' => false,
                'message' => 'Kecamatan tidak ditemukan'
            ], 404);
        }

        try {
            $kecamatan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus Kecamatan'
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus Kecamatan'
            ], 500);
        }
    }

    /**
     * Endpoint khusus untuk ambil kecamatan berdasarkan ID Kota/Kabupaten
     */
    public function getByKota($regency_id)
    {
        $kecamatan = Kecamatan::where('regency_id', $regency_id)
            ->with(['kelurahans', 'kotaKabupaten'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data kecamatan berdasarkan kota/kabupaten',
            'data' => $kecamatan,
        ], 200);
    }
}