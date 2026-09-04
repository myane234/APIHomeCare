<?php

namespace App\Http\Controllers;

use App\Models\MasterMetodePembayaran;
use App\Models\MasterKategoriPembayaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
     *          "id_metode": 1,
     *          "id_kategori_pembayaran": 1,
     *          "nama_metode": "BCA Transfer",
     *          "tipe_potongan": "nominal",
     *          "nilai_potongan": 0,
     *          "logo": "http://localhost/storage/metode_pembayaran/bca.png",
     *          "is_active": true,
     *          "created_at": "2022-01-01T00:00:00.000000Z",
     *          "updated_at": "2022-01-01T00:00:00.000000Z"
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $data = MasterMetodePembayaran::with('kategori')
            ->whereNotNull('payment_type')
            ->where('is_active', true)
            ->whereHas('kategori', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        $data->each(function ($item) {
            $item->logo = $item->logo ? url(Storage::url($item->logo)) : null;
        });

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar metode pembayaran',
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
     * @bodyParam logo file optional File logo/gambar metode pembayaran
     * @bodyParam is_active boolean optional Status metode pembayaran
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kategori_pembayaran' => 'required|exists:master_kategori_pembayaran,id_kategori_pembayaran',
            'payment_type' => ['required', 'string', 'max:50', 'unique:master_metode_pembayaran,payment_type'],
            'nama_metode' => 'required|string|max:255',
            'tipe_potongan' => 'required|in:nominal,persen',
            'nilai_potongan' => 'required|numeric|min:0',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('metode_pembayaran', 'public');
            $validated['logo'] = $path;
        }

        $metode = MasterMetodePembayaran::create($validated);
        $metode->load('kategori');
        
        $metode->logo = $metode->logo ? url(Storage::url($metode->logo)) : null;

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil ditambahkan',
            'data' => $metode
        ], 201);
    }

    /**
     * Get master metode pembayaran by id
     */
    public function show($id)
    {
        $metode = MasterMetodePembayaran::with('kategori')->findOrFail($id);
        $metode->logo = $metode->logo ? url(Storage::url($metode->logo)) : null;

        return response()->json([
            'success' => true,
            'message' => 'Detail metode pembayaran',
            'data' => $metode
        ], 200);
    }

    /**
     * Update master metode pembayaran by id
     */
    public function update(Request $request, $id)
    {
        $metode = MasterMetodePembayaran::findOrFail($id);

        $validated = $request->validate([
            'id_kategori_pembayaran' => 'sometimes|required|exists:master_kategori_pembayaran,id_kategori_pembayaran',
            'payment_type' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('master_metode_pembayaran', 'payment_type')->ignore($metode->id_metode, 'id_metode'),
            ],
            'nama_metode' => 'sometimes|required|string|max:255',
            'tipe_potongan' => 'sometimes|required|in:nominal,persen',
            'nilai_potongan' => 'sometimes|required|numeric|min:0',
            'logo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            if ($metode->logo) {
                Storage::disk('public')->delete($metode->logo);
            }
            $path = $request->file('logo')->store('metode_pembayaran', 'public');
            $validated['logo'] = $path;
        }

        $metode->update($validated);
        $metode->load('kategori');

        $metode->logo = $metode->logo ? url(Storage::url($metode->logo)) : null;

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil diperbarui',
            'data' => $metode
        ], 200);
    }

    /**
     * Delete master metode pembayaran by id
     */
    public function destroy($id)
    {
        $metode = MasterMetodePembayaran::findOrFail($id);

        if ($metode->logo) {
            Storage::disk('public')->delete($metode->logo);
        }

        $metode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil dihapus'
        ], 200);
    }
}
