<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\KategoriLayanan;

/**
 * Master Kategori Layanan
 * 
 * @group Master Data
 * 
 * @subgroup Master Kategori Layanan
 * 
 * @resource Master Kategori Layanan
 */

class KategoriLayananController extends Controller
{
    /**
     * Get all master kategori layanan
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar kategori layanan",
     *  "data": [
     *      {
     *          "id_kategori_layanan": 1,
     *          "nama_kategori": "Layanan Medis",
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $kategori = KategoriLayanan::all();
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar kategori layanan',
            'data' => $kategori
        ], 200);
    }

    /**
     * Tambah kategori layanan baru (Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:255', 'unique:kategori_layanans,nama_kategori'],
        ]);

        $kategori = KategoriLayanan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil ditambahkan',
            'data' => $kategori
        ], 201);
    }

    /**
     * Tampilkan detail kategori layanan (Admin only).
     */
    public function show($id)
    {
        $kategori = KategoriLayanan::findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail kategori layanan',
            'data' => $kategori
        ], 200);
    }

    /**
     * Update kategori layanan (Admin only).
     */
    public function update(Request $request, $id)
    {
        $kategori = KategoriLayanan::findOrFail($id);

        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:255', 'unique:kategori_layanans,nama_kategori,' . $id . ',id_kategori_layanan'],
        ]);

        $kategori->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil diupdate',
            'data' => $kategori
        ], 200);
    }

    /**
     * Hapus kategori layanan (Admin only).
     */
    public function destroy($id)
    {
        $kategori = KategoriLayanan::findOrFail($id);
        $kategori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil dihapus'
        ], 200);
    }
}
