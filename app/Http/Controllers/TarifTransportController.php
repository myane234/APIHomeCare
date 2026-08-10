<?php

namespace App\Http\Controllers;

use App\Models\MasterTarifTransport;
use Illuminate\Http\Request;


/**
 * Master Tarif Transport
 * 
 * @group Master Data
 * 
 * @subgroup Master Tarif Transport
 * 
 * @resource Master Tarif Transport
 */
class TarifTransportController extends Controller
{
    /**
     * Get all master tarif transport
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar tarif transport",
     *  "data": [
     *      {
     *          "id_tarif_transport": 1,
     *          "id_kota": 1,
     *          "tarif_awal": 10000,
     *          "tarif_per_kilometer": 2000,
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $data = MasterTarifTransport::with('kota')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar tarif transport',
            'data' => $data
        ], 200);
    }

    /**
     * Store a new master tarif transport
     * 
     * @bodyParam id_kota int required ID kota
     * @bodyParam tarif_awal numeric required Tarif awal
     * @bodyParam tarif_per_kilometer numeric required Tarif per kilometer
     * 
     * @response 201 {
     *  "success": true,
     *  "message": "Tarif transport berhasil ditambahkan",
     *  "data": {
     *      "id_tarif_transport": 1,
     *      "id_kota": 1,
     *      "tarif_awal": 10000,
     *      "tarif_per_kilometer": 2000,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kota' => 'required|exists:master_kota_kabupaten,id_kota',
            'tarif_awal' => 'required|numeric|min:0',
            'tarif_per_kilometer' => 'required|numeric|min:0',
        ]);

        $transport = MasterTarifTransport::create($validated);
        $transport->load('kota');

        return response()->json([
            'success' => true,
            'message' => 'Tarif transport berhasil ditambahkan',
            'data' => $transport
        ], 201);
    }

    /**
     * Get master tarif transport by id 
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Detail tarif transport",
     *  "data": {
     *      "id_tarif_transport": 1,
     *      "id_kota": 1,
     *      "tarif_awal": 10000,
     *      "tarif_per_kilometer": 2000,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */
    public function show($id)
    {
        $transport = MasterTarifTransport::with('kota')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail tarif transport',
            'data' => $transport
        ], 200);
    }

    /**
     * Update master tarif transport by id
     * 
     * @bodyParam id_kota int required ID kota
     * @bodyParam tarif_awal numeric required Tarif awal
     * @bodyParam tarif_per_kilometer numeric required Tarif per kilometer
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Tarif transport berhasil diperbarui",
     *  "data": {
     *      "id_tarif_transport": 1,
     *      "id_kota": 1,
     *      "tarif_awal": 10000,
     *      "tarif_per_kilometer": 2000,
     *      "created_at": "2022-01-01T00:00:00.000000Z",
     *      "updated_at": "2022-01-01T00:00:00.000000Z"
     *  }
     * }
     */

    public function update(Request $request, $id)
    {
        $transport = MasterTarifTransport::findOrFail($id);

        $validated = $request->validate([
            'id_kota' => 'sometimes|required|exists:master_kota_kabupaten,id_kota',
            'tarif_awal' => 'sometimes|required|numeric|min:0',
            'tarif_per_kilometer' => 'sometimes|required|numeric|min:0',
        ]);

        $transport->update($validated);
        $transport->load('kota');

        return response()->json([
            'success' => true,
            'message' => 'Tarif transport berhasil diperbarui',
            'data' => $transport
        ], 200);
    }

    /**
     * Delete master tarif transport by id
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Tarif transport berhasil dihapus"
     * }
     */

    public function destroy($id)
    {
        $transport = MasterTarifTransport::findOrFail($id);
        $transport->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tarif transport berhasil dihapus'
        ], 200);
    }
}
