<?php

namespace App\Http\Controllers;

use App\Models\MasterLayanan;
use App\Models\BhpItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mapping BHP per Layanan
 * 
 * @group Master Data
 * 
 * @subgroup Mapping BHP per Layanan
 * 
 * @resource Mapping BHP per Layanan
 */
class MappingLayananBhpController extends Controller
{
    /**
     * Get list layanan dengan mapping BHP-nya
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil mapping layanan dengan BHP",
     *  "data": [
     *      {
     *          "id_layanan": 1,
     *          "nama_layanan": "Infus Whitening",
     *          "bhp_items": [
     *              {
     *                  "id_bhp": 1,
     *                  "nama_bhp": "Jarum Suntik",
     *                  "qty_default": 2,
     *                  "is_mandatory": true
     *              }
     *          ]
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $layanans = MasterLayanan::with('bhpItems')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil mapping layanan dengan BHP',
            'data' => $layanans
        ], 200);
    }

    /**
     * Tampilkan detail mapping BHP pada satu Layanan
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Detail mapping BHP untuk layanan terpilih",
     *  "data": {
     *      "id_layanan": 1,
     *      "nama_layanan": "Infus Whitening",
     *      "bhp_items": [
     *          {
     *              "id_bhp": 1,
     *              "nama_bhp": "Jarum Suntik",
     *              "qty_default": 2,
     *              "is_mandatory": true
     *          }
     *      ]
     *  }
     * }
     */
    public function show($id_layanan)
    {
        $layanan = MasterLayanan::with('bhpItems')->findOrFail($id_layanan);

        return response()->json([
            'success' => true,
            'message' => 'Detail mapping BHP untuk layanan terpilih',
            'data' => $layanan
        ], 200);
    }

    /**
     * Sync (Update keseluruhan) mapping BHP untuk suatu layanan
     * 
     * @bodyParam bhp_items array required Daftar item BHP
     * @bodyParam bhp_items.*.id_bhp int required ID BHP
     * @bodyParam bhp_items.*.qty_default int optional Jumlah default BHP (default: 1)
     * @bodyParam bhp_items.*.is_mandatory boolean optional Apakah wajib digunakan (default: true)
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Mapping BHP untuk layanan berhasil disinkronisasi",
     *  "data": {
     *      "id_layanan": 1,
     *      "nama_layanan": "Infus Whitening",
     *      "bhp_items": []
     *  }
     * }
     */
    public function sync(Request $request, $id_layanan)
    {
        $layanan = MasterLayanan::findOrFail($id_layanan);

        $request->validate([
            'bhp_items' => 'present|array',
            'bhp_items.*.id_bhp' => 'required|exists:master_bhp,id_bhp',
            'bhp_items.*.qty_default' => 'nullable|integer|min:1',
            'bhp_items.*.is_mandatory' => 'nullable|boolean',
        ]);

        $syncData = [];
        if (!empty($request->bhp_items)) {
            foreach ($request->bhp_items as $item) {
                $syncData[$item['id_bhp']] = [
                    'qty_default' => $item['qty_default'] ?? 1,
                    'is_mandatory' => $item['is_mandatory'] ?? true,
                ];
            }
        }

        DB::beginTransaction();
        try {
            $layanan->bhpItems()->sync($syncData);
            DB::commit();

            // Load updated relations
            $layanan->load('bhpItems');

            return response()->json([
                'success' => true,
                'message' => 'Mapping BHP untuk layanan berhasil disinkronisasi',
                'data' => $layanan
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal sinkronisasi mapping: ' . $e->getMessage()
            ], 500);
        }
    }
}
