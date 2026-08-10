<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MasterKategoriPembayaran;

/**
 * Master Kategori Pembayaran
 * 
 * @group Master Data
 * 
 * @subgroup Master Kategori Pembayaran
 * 
 * @resource Master Kategori Pembayaran
 */
class KategoriPembayaranController extends Controller
{
    /**
     * Get all master kategori pembayaran
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar kategori pembayaran",
     *  "data": [
     *      {
     *          "id_kategori_pembayaran": 1,
     *          "nama_kategori": "Bank Transfer",
     *          "is_active": true,
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $kategori = MasterKategoriPembayaran::all();
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar kategori pembayaran',
            'data' => $kategori
        ], 200);
    }

    /**
     * Tambah kategori pembayaran baru (Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:255', 'unique:master_kategori_pembayaran,nama_kategori'],
            'is_active' => ['boolean']
        ]);

        $kategori = MasterKategoriPembayaran::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori pembayaran berhasil ditambahkan',
            'data' => $kategori
        ], 201);
    }

    /**
     * Tampilkan detail kategori pembayaran (Admin only).
     */
    public function show($id)
    {
        $kategori = MasterKategoriPembayaran::findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail kategori pembayaran',
            'data' => $kategori
        ], 200);
    }

    /**
     * Update kategori pembayaran (Admin only).
     */
    public function update(Request $request, $id)
    {
        $kategori = MasterKategoriPembayaran::findOrFail($id);

        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:255', 'unique:master_kategori_pembayaran,nama_kategori,' . $id . ',id_kategori_pembayaran'],
            'is_active' => ['boolean']
        ]);

        $kategori->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori pembayaran berhasil diupdate',
            'data' => $kategori
        ], 200);
    }

    /**
     * Hapus kategori pembayaran (Admin only).
     */
    public function destroy($id)
    {
        $kategori = MasterKategoriPembayaran::findOrFail($id);
        $kategori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori pembayaran berhasil dihapus'
        ], 200);
    }
}
