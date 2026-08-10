<?php

namespace App\Http\Controllers;

use App\Models\MasterTarifTransport;
use Illuminate\Http\Request;

class TarifTransportController extends Controller
{
    public function index()
    {
        $data = MasterTarifTransport::with('kota')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar tarif transport',
            'data' => $data
        ], 200);
    }

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

    public function show($id)
    {
        $transport = MasterTarifTransport::with('kota')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail tarif transport',
            'data' => $transport
        ], 200);
    }

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
