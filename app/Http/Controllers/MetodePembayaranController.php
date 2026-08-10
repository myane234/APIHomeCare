<?php

namespace App\Http\Controllers;

use App\Models\MasterMetodePembayaran;
use App\Models\MasterKategoriPembayaran;
use Illuminate\Http\Request;

class MetodePembayaranController extends Controller
{
    public function index()
    {
        // Mengembalikan daftar metode pembayaran beserta kategorinya
        $data = MasterMetodePembayaran::with('kategori')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar metode pembayaran',
            'data' => $data
        ], 200);
    }

    public function categories()
    {
        // Mengambil daftar kategori pembayaran
        $data = MasterKategoriPembayaran::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar kategori pembayaran',
            'data' => $data
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kategori_pembayaran' => 'required|exists:master_kategori_pembayaran,id_kategori_pembayaran',
            'nama_metode' => 'required|string|max:255',
            'tipe_potongan' => 'required|in:nominal,persen',
            'nilai_potongan' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $metode = MasterMetodePembayaran::create($validated);
        $metode->load('kategori');

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil ditambahkan',
            'data' => $metode
        ], 201);
    }

    public function show($id)
    {
        $metode = MasterMetodePembayaran::with('kategori')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail metode pembayaran',
            'data' => $metode
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $metode = MasterMetodePembayaran::findOrFail($id);

        $validated = $request->validate([
            'id_kategori_pembayaran' => 'sometimes|required|exists:master_kategori_pembayaran,id_kategori_pembayaran',
            'nama_metode' => 'sometimes|required|string|max:255',
            'tipe_potongan' => 'sometimes|required|in:nominal,persen',
            'nilai_potongan' => 'sometimes|required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $metode->update($validated);
        $metode->load('kategori');

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil diperbarui',
            'data' => $metode
        ], 200);
    }

    public function destroy($id)
    {
        $metode = MasterMetodePembayaran::findOrFail($id);
        $metode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil dihapus'
        ], 200);
    }
}
