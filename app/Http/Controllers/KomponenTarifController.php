<?php

namespace App\Http\Controllers;

use App\Models\MasterKomponenBiaya;
use Illuminate\Http\Request;

/**
 * Master Komponen Tarif
 * 
 * @group Master Data
 * 
 * @subgroup Master Komponen Tarif
 * 
 * @resource Master Komponen Tarif
 */

class KomponenTarifController extends Controller
{
    /**
     * Get all master komponen tarif
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar komponen biaya",
     *  "data": [
     *      {
     *          "id_komponen": 1,
     *          "nama_komponen": "Pajak",
     *          "tipe_komponen": "pajak",
     *          "jenis_nilai": "persen",
     *          "nilai": 11,
     *          "is_active": true,
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $data = MasterKomponenBiaya::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar komponen biaya',
            'data' => $data
        ], 200);
    }

    /**
     * Store a new master komponen tarif
     * 
     * @bodyParam nama_komponen string required Nama komponen biaya
     * @bodyParam tipe_komponen string required Tipe komponen biaya (pajak, admin_aplikasi, asuransi, lainnya)
     * @bodyParam jenis_nilai string required Jenis nilai (nominal, persen)
     * @bodyParam nilai numeric required Nilai komponen biaya
     * @bodyParam is_active boolean optional Status komponen biaya
     * 
     * @response 201 {
     *  "success": true,
     *  "message": "Komponen biaya berhasil ditambahkan",
     *  "data": {
     *      "id_komponen": 1,
     *      "nama_komponen": "Pajak",
     *      "tipe_komponen": "pajak",
     *      "jenis_nilai": "persen",
     *      "nilai": 11,
     *      "is_active": true,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_komponen' => 'required|string|max:255',
            'tipe_komponen' => 'required|in:pajak,admin_aplikasi,asuransi,lainnya',
            'jenis_nilai' => 'required|in:nominal,persen',
            'nilai' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $komponen = MasterKomponenBiaya::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya berhasil ditambahkan',
            'data' => $komponen
        ], 201);
    }

    /**
     * Get master komponen tarif by id
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Detail komponen biaya",
     *  "data": {
     *      "id_komponen": 1,
     *      "nama_komponen": "Pajak",
     *      "tipe_komponen": "pajak",
     *      "jenis_nilai": "persen",
     *      "nilai": 11,
     *      "is_active": true,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */

    public function show($id)
    {
        $komponen = MasterKomponenBiaya::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail komponen biaya',
            'data' => $komponen
        ], 200);
    }

    /**
     * Update master komponen tarif by id
     * 
     * @bodyParam nama_komponen string required Nama komponen biaya
     * @bodyParam tipe_komponen string required Tipe komponen biaya (pajak, admin_aplikasi, asuransi, lainnya)
     * @bodyParam jenis_nilai string required Jenis nilai (nominal, persen)
     * @bodyParam nilai numeric required Nilai komponen biaya
     * @bodyParam is_active boolean optional Status komponen biaya
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Komponen biaya berhasil diperbarui",
     *  "data": {
     *      "id_komponen": 1,
     *      "nama_komponen": "Pajak",
     *      "tipe_komponen": "pajak",
     *      "jenis_nilai": "persen",
     *      "nilai": 11,
     *      "is_active": true,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */

    public function update(Request $request, $id)
    {
        $komponen = MasterKomponenBiaya::findOrFail($id);

        $validated = $request->validate([
            'nama_komponen' => 'sometimes|required|string|max:255',
            'tipe_komponen' => 'sometimes|required|in:pajak,admin_aplikasi,asuransi,lainnya',
            'jenis_nilai' => 'sometimes|required|in:nominal,persen',
            'nilai' => 'sometimes|required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $komponen->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya berhasil diperbarui',
            'data' => $komponen
        ], 200);
    }

    /***
     * Delete master komponen tarif by id
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Komponen biaya berhasil dihapus"
     * }
     */

    public function destroy($id)
    {
        $komponen = MasterKomponenBiaya::findOrFail($id);
        $komponen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya berhasil dihapus'
        ], 200);
    }
}
