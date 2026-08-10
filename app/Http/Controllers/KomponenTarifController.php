<?php

namespace App\Http\Controllers;

use App\Models\MasterKomponenBiaya;
use Illuminate\Http\Request;

class KomponenTarifController extends Controller
{
    public function index()
    {
        $data = MasterKomponenBiaya::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar komponen biaya',
            'data' => $data
        ], 200);
    }

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

    public function show($id)
    {
        $komponen = MasterKomponenBiaya::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail komponen biaya',
            'data' => $komponen
        ], 200);
    }

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
