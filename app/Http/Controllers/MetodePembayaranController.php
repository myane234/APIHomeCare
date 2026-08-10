<?php

namespace App\Http\Controllers;

use App\Models\MasterMetodePembayaran;
use App\Models\MasterKategoriPembayaran;
use Illuminate\Http\Request;

/**
 * Master Metode Pembayaran
 * 
 * @group Master Data
 * 
 * @subgroup Master Metode Pembayaran
 * 
 * @resource Master Metode Pembayaran
 */
class MetodePembayaranController extends Controller
{
    /**
     * Get all master metode pembayaran
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar metode pembayaran",
     *  "data": [
     *      {
     *          "id_metode_pembayaran": 1,
     *          "id_kategori_pembayaran": 1,
     *          "nama_metode": "Transfer Bank",
     *          "tipe_potongan": "nominal",
     *          "nilai_potongan": 0,
     *          "is_active": true,
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */
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

    /**
     * Get all master kategori pembayaran
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar kategori pembayaran",
     *  "data": [
     *      {
     *          "id_kategori_pembayaran": 1,
     *          "nama_kategori": "Transfer Bank",
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */

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

    /**
     * Store a new master metode pembayaran
     * 
     * @bodyParam id_kategori_pembayaran int required ID kategori pembayaran
     * @bodyParam nama_metode string required Nama metode pembayaran
     * @bodyParam tipe_potongan string required Tipe potongan (nominal, persen)
     * @bodyParam nilai_potongan numeric required Nilai potongan
     * @bodyParam is_active boolean optional Status metode pembayaran
     * 
     * @response 201 {
     *  "success": true,
     *  "message": "Metode pembayaran berhasil ditambahkan",
     *  "data": {
     *      "id_metode_pembayaran": 1,
     *      "id_kategori_pembayaran": 1,
     *      "nama_metode": "Transfer Bank",
     *      "tipe_potongan": "nominal",
     *      "nilai_potongan": 0,
     *      "is_active": true,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */

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


    /**
     * Get master metode pembayaran by id
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Detail metode pembayaran",
     *  "data": {
     *      "id_metode_pembayaran": 1,
     *      "id_kategori_pembayaran": 1,
     *      "nama_metode": "Transfer Bank",
     *      "tipe_potongan": "nominal",
     *      "nilai_potongan": 0,
     *      "is_active": true,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */

    public function show($id)
    {
        $metode = MasterMetodePembayaran::with('kategori')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail metode pembayaran',
            'data' => $metode
        ], 200);
    }

    /**
     * Update master metode pembayaran by id
     * 
     * @bodyParam id_kategori_pembayaran int required ID kategori pembayaran
     * @bodyParam nama_metode string required Nama metode pembayaran
     * @bodyParam tipe_potongan string required Tipe potongan (nominal, persen)
     * @bodyParam nilai_potongan numeric required Nilai potongan
     * @bodyParam is_active boolean optional Status metode pembayaran
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Metode pembayaran berhasil diperbarui",
     *  "data": {
     *      "id_metode_pembayaran": 1,
     *      "id_kategori_pembayaran": 1,
     *      "nama_metode": "Transfer Bank",
     *      "tipe_potongan": "nominal",
     *      "nilai_potongan": 0,
     *      "is_active": true,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */
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

    /**
     * Delete master metode pembayaran by id
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Metode pembayaran berhasil dihapus"
     * }
     */

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
