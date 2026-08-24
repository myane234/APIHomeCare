<?php

namespace App\Http\Controllers;

use App\Models\MasterKategoriTarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterKategoriTarifController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil kategori tarif',
            'data' => MasterKategoriTarif::orderByDesc('is_default')->orderBy('nama_kategori')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:master_kategori_tarif,nama_kategori'],
            'biaya_tambahan' => ['required', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated) {
            if (($validated['is_default'] ?? false) === true) {
                MasterKategoriTarif::query()->update(['is_default' => false]);
            }

            $kategori = MasterKategoriTarif::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Kategori tarif berhasil ditambahkan',
                'data' => $kategori,
            ], 201);
        });
    }

    public function show(MasterKategoriTarif $masterKategoriTarif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail kategori tarif',
            'data' => $masterKategoriTarif,
        ]);
    }

    public function update(Request $request, MasterKategoriTarif $masterKategoriTarif)
    {
        $validated = $request->validate([
            'nama_kategori' => ['sometimes', 'required', 'string', 'max:100', 'unique:master_kategori_tarif,nama_kategori,' . $masterKategoriTarif->id_kategori_tarif . ',id_kategori_tarif'],
            'biaya_tambahan' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated, $masterKategoriTarif) {
            if (($validated['is_default'] ?? false) === true) {
                MasterKategoriTarif::where('id_kategori_tarif', '!=', $masterKategoriTarif->getKey())
                    ->update(['is_default' => false]);
            }

            $masterKategoriTarif->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Kategori tarif berhasil diubah',
                'data' => $masterKategoriTarif->fresh(),
            ]);
        });
    }

    public function destroy(MasterKategoriTarif $masterKategoriTarif)
    {
        if ($masterKategoriTarif->masterTarifs()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tarif tidak dapat dihapus karena masih digunakan oleh master tarif',
            ], 422);
        }

        $masterKategoriTarif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori tarif berhasil dihapus',
        ]);
    }
}
