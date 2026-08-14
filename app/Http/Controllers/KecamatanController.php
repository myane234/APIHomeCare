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
    public function index()
    {
        $data = Kecamatan::with('kelurahans')->get();
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar Kecamatan',
            'data' => $data,
            'total' => count($data)
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kecamatan' => ['required', 'string', 'unique:master_kecamatan,id_kecamatan'],
            'regency_id' => ['nullable', 'string'],
            'nama_kecamatan' => ['required', 'string', 'max:255'],
        ]);

        try {
            $kecamatan = Kecamatan::create($validated);
            return response()->json([
                'success' => true,
                'message' => 'Berhasil menambahkan Kecamatan',
                'data' => $kecamatan
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
        $kecamatan = Kecamatan::with('kelurahans')->find($id);

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
            'regency_id' => ['nullable', 'string'],
            'nama_kecamatan' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        try {
            $kecamatan->update($validated);
            return response()->json([
                'success' => true,
                'message' => 'Berhasil memperbarui Kecamatan',
                'data' => $kecamatan
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
}
