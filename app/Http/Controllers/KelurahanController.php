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

        // Optional filter by id_kecamatan
        if ($request->has('id_kecamatan')) {
            $query->where('id_kecamatan', $request->input('id_kecamatan'));
        }

        $data = $query->with('kecamatan')->paginate($perPage);

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
            'id_kelurahan' => ['required', 'string', 'unique:master_kelurahan,id_kelurahan'],
            'id_kecamatan' => ['required', 'string', 'exists:master_kecamatan,id_kecamatan'],
            'nama_kelurahan' => ['required', 'string', 'max:255'],
        ]);

        try {
            $kelurahan = Kelurahan::create($validated);
            return response()->json([
                'success' => true,
                'message' => 'Berhasil menambahkan Kelurahan',
                'data' => $kelurahan->load('kecamatan')
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan Kelurahan'
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
