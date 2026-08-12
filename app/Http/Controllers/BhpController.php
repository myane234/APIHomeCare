<?php

namespace App\Http\Controllers;

use App\Models\BhpItem;
use Illuminate\Http\Request;

/**
 * Master BHP Item
 * 
 * @group Master Data
 * 
 * @subgroup Master BHP Item
 * 
 * @resource Master BHP Item
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
     *          "harga_modal": 1500.00,
     *          "harga_jual": 5000.00,
     *          "is_active": true,
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
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
     * 
     * @bodyParam nama_bhp string required Nama BHP (e.g. Spuit, Kassa)
     * @bodyParam tipe_bhp string required Tipe BHP (satuan, paket)
     * @bodyParam harga_modal numeric required Harga pokok/modal BHP
     * @bodyParam harga_jual numeric required Harga jual ke pasien
     * @bodyParam is_active boolean optional Status keaktifan BHP
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
     * 
     * @bodyParam nama_bhp string optional Nama BHP
     * @bodyParam tipe_bhp string optional Tipe BHP
     * @bodyParam harga_modal numeric optional Harga pokok/modal BHP
     * @bodyParam harga_jual numeric optional Harga jual ke pasien
     * @bodyParam is_active boolean optional Status keaktifan BHP
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
