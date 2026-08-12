<?php

namespace App\Http\Controllers;

use App\Models\MasterKomponenBiaya;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
     * Tipe Komponen yang Tersedia 
     */
    private const TIPE_KOMPONEN_LIST = [
        'pajak' => 'Pajak (PPN/PPh)',
        'admin_aplikasi' => 'Biaya Admin Aplikasi',
        'lainnya' => 'Lain-lain',
    ];

    /**
     * Get options list untuk Dropdown di Frontend
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil opsi pilihan tipe komponen",
     *  "data": {
     *      "tipe_komponen": [
     *          {"value": "pajak", "label": "Pajak (PPN/PPh)"},
     *          {"value": "admin_aplikasi", "label": "Biaya Admin Aplikasi"},
     *          {"value": "lainnya", "label": "Lain-lain"}
     *      ],
     *      "jenis_nilai": [
     *          {"value": "nominal", "label": "Nominal (Rp)"},
     *          {"value": "persen", "label": "Persentase (%)"}
     *      ]
     *  }
     * }
     */
    public function KategoriKomponenTarif()
    {
        $tipeOptions = [];
        foreach (self::TIPE_KOMPONEN_LIST as $value => $label) {
            $tipeOptions[] = ['value' => $value, 'label' => $label];
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil opsi pilihan tipe komponen',
            'data' => [
                'tipe_komponen' => $tipeOptions,
                'jenis_nilai' => [
                    ['value' => 'nominal', 'label' => 'Nominal (Rp)'],
                    ['value' => 'persen', 'label' => 'Persentase (%)'],
                ]
            ]
        ], 200);
    }

    /**
     * Get all master komponen tarif
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
     * @bodyParam nama_komponen string required Nama komponen biaya (e.g. PPN, Biaya Aplikasi)
     * @bodyParam tipe_komponen string required Tipe komponen biaya (pajak, admin_aplikasi, lainnya)
     * @bodyParam jenis_nilai string required Jenis nilai (nominal, persen)
     * @bodyParam nilai numeric required Nilai komponen biaya
     * @bodyParam is_active boolean optional Status keaktifan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_komponen' => 'required|string|max:255',
            'tipe_komponen' => ['required', Rule::in(array_keys(self::TIPE_KOMPONEN_LIST))],
            'jenis_nilai' => 'required|in:nominal,persen',
            'nilai' => [
                'required',
                'numeric',
                'min:0',
                $request->jenis_nilai === 'persen' ? 'max:100' : 'max:9999999999'
            ],
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
     * Get master komponen tarif by ID
     */
    public function show($id)
    {
        $komponen = MasterKomponenBiaya::find($id);

        if (!$komponen) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail komponen biaya',
            'data' => $komponen
        ], 200);
    }

    /**
     * Update master komponen tarif by ID
     */
    public function update(Request $request, $id)
    {
        $komponen = MasterKomponenBiaya::find($id);

        if (!$komponen) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'nama_komponen' => 'sometimes|required|string|max:255',
            'tipe_komponen' => ['sometimes', 'required', Rule::in(array_keys(self::TIPE_KOMPONEN_LIST))],
            'jenis_nilai' => 'sometimes|required|in:nominal,persen',
            'nilai' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                $request->jenis_nilai === 'persen' ? 'max:100' : 'max:9999999999'
            ],
            'is_active' => 'boolean',
        ]);

        $komponen->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya berhasil diperbarui',
            'data' => $komponen
        ], 200);
    }

    /**
     * Delete master komponen tarif by ID
     */
    public function destroy($id)
    {
        $komponen = MasterKomponenBiaya::find($id);

        if (!$komponen) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya tidak ditemukan'
            ], 404);
        }

        $komponen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya berhasil dihapus'
        ], 200);
    }
}