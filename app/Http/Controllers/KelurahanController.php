<?php

namespace App\Http\Controllers;

use App\Models\Kelurahan;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * @group API Master Kelurahan
 */

class KelurahanController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);

        $query = Kelurahan::query();

        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', $request->input('id_kecamatan'));
        }

      
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelurahan', 'LIKE', "%{$search}%")
                  ->orWhereHas('kecamatan', function ($qKec) use ($search) {
                      $qKec->where('nama_kecamatan', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Eager load kecamatan + kotaKabupaten
        $data = $query->with('kecamatan.kotaKabupaten')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar Kelurahan',
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
            'nama_kota' => ['required', 'string'],
            'nama_kecamatan' => ['required', 'string', 'max:255'],
        ]);

        $kota = KotaKabupaten::where('nama_kota', $request->nama_kota)->first();

        if (!$kota) {
            return response()->json([
                'success' => false,
                'message' => 'Kota/Kabupaten "' . $request->nama_kota . '" tidak ditemukan di database!'
            ], 404);
        }

        try {
            $kecamatan = Kecamatan::create([
                'id_kecamatan' => $request->id_kecamatan,
                'regency_id' => $kota->id_kota,
                'nama_kecamatan' => $request->nama_kecamatan,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menambahkan Kecamatan',
                'data' => $kecamatan->load('kotaKabupaten')
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
        $kelurahan = Kelurahan::with('kecamatan')->find($id);

        if (!$kelurahan) {
            return response()->json([
                'success' => false,
                'message' => 'Kelurahan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data Kelurahan',
            'data' => $kelurahan
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $kelurahan = Kelurahan::find($id);

        if (!$kelurahan) {
            return response()->json([
                'success' => false,
                'message' => 'Kelurahan tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'id_kecamatan' => ['sometimes', 'required', 'string', 'exists:master_kecamatan,id_kecamatan'],
            'nama_kelurahan' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        try {
            $kelurahan->update($validated);
            return response()->json([
                'success' => true,
                'message' => 'Berhasil memperbarui Kelurahan',
                'data' => $kelurahan->load('kecamatan')
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui Kelurahan'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $kelurahan = Kelurahan::find($id);

        if (!$kelurahan) {
            return response()->json([
                'success' => false,
                'message' => 'Kelurahan tidak ditemukan'
            ], 404);
        }

        try {
            $kelurahan->delete();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus Kelurahan'
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus Kelurahan'
            ], 500);
        }
    }
}
