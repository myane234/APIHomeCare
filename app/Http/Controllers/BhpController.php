<?php

namespace App\Http\Controllers;

use App\Models\BhpItem;
use Illuminate\Http\Request;

/**
 * @group BhpController
 */
class BhpController extends Controller
{
    /**
     * Get all master BHP
     *
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil daftar BHP",
     *  "data": [
     *      {
     *          "id_bhp": 1,
     *          "nama_bhp": "Suntikan 5ml",
     *          "tipe_bhp": "satuan",
     *          "harga_modal": 1500,
     *          "harga_jual": 5000,
     *          "is_active": true
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $data = BhpItem::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar BHP',
            'data' => $data
        ], 200);
    }

    /**
     * Store new master BHP
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bhp' => 'required|string|max:255',
            'tipe_bhp' => 'required|in:satuan,paket',
            'harga_modal' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $bhp = BhpItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'BHP berhasil ditambahkan',
            'data' => $bhp
        ], 201);
    }

    /**
     * Show master BHP by ID
     */
    public function show($id)
    {
        $bhp = BhpItem::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail BHP',
            'data' => $bhp
        ], 200);
    }

    /**
     * Update master BHP
     */
    public function update(Request $request, $id)
    {
        $bhp = BhpItem::findOrFail($id);

        $validated = $request->validate([
            'nama_bhp' => 'sometimes|required|string|max:255',
            'tipe_bhp' => 'sometimes|required|in:satuan,paket',
            'harga_modal' => 'sometimes|required|numeric|min:0',
            'harga_jual' => 'sometimes|required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $bhp->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'BHP berhasil diperbarui',
            'data' => $bhp
        ], 200);
    }

    /**
     * Delete master BHP
     */
    public function destroy($id)
    {
        $bhp = BhpItem::findOrFail($id);
        $bhp->delete();

        return response()->json([
            'success' => true,
            'message' => 'BHP berhasil dihapus'
        ], 200);
    }
}
