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
    * Get the national transport tariff configuration
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar tarif transport",
    *  "data": {
    *      "id_transport": 1,
    *      "tarif_per_10_km": 20000.00
    *  }
     * }
     */
    public function index()
    {
        $data = MasterTarifTransport::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar tarif transport',
            'data' => $data
        ], 200);
    }

    /**
    * Create or update the national transport tariff configuration
    *
    * @bodyParam tarif_per_10_km numeric required Tarif untuk setiap kelipatan 10 km
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tarif_per_10_km' => 'required|numeric|min:0',
        ]);

        $transport = MasterTarifTransport::query()->first();
        if ($transport) {
            $transport->update($validated);
        } else {
            $transport = MasterTarifTransport::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tarif transport berhasil ditambahkan',
            'data' => $transport
        ], 201);
    }

    /**
     * Get master tarif transport by ID
     */
    public function show($id)
    {
        $transport = MasterTarifTransport::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail tarif transport',
            'data' => $transport
        ], 200);
    }

    /**
    * Update the national transport tariff configuration
    *
    * @bodyParam tarif_per_10_km numeric optional Tarif untuk setiap kelipatan 10 km
     */
    public function update(Request $request, $id)
    {
        $transport = MasterTarifTransport::findOrFail($id);

        $validated = $request->validate([
            'tarif_per_10_km' => 'sometimes|required|numeric|min:0',
        ]);

        $transport->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tarif transport berhasil diperbarui',
            'data' => $transport
        ], 200);
    }

    /**
     * Delete master tarif transport by ID
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
