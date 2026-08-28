<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KategoriLayanan;
use Illuminate\Support\Facades\Storage;

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
     *          "photo_kategori": "kategori_layanan/example.jpg",
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
            'nama_kategori'  => ['required', 'string', 'max:255', 'unique:kategori_layanans,nama_kategori'],
            'photo_kategori' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        if ($request->hasFile('photo_kategori')) {
            $validated['photo_kategori'] = $request->file('photo_kategori')->store('kategori_layanan', 'public');
        }

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
            'nama_kategori'  => ['required', 'string', 'max:255', 'unique:kategori_layanans,nama_kategori,' . $id . ',id_kategori_layanan'],
            'photo_kategori' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        if ($request->hasFile('photo_kategori')) {
            // Hapus foto lama jika ada di storage
            if ($kategori->photo_kategori && Storage::disk('public')->exists($kategori->photo_kategori)) {
                Storage::disk('public')->delete($kategori->photo_kategori);
            }

            $validated['photo_kategori'] = $request->file('photo_kategori')->store('kategori_layanan', 'public');
        }

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

        // Hapus foto dari storage saat data dihapus
        if ($kategori->photo_kategori && Storage::disk('public')->exists($kategori->photo_kategori)) {
            Storage::disk('public')->delete($kategori->photo_kategori);
        }

        $kategori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil dihapus'
        ], 200);
    }
}