<?php

namespace App\Http\Controllers\SuperAdminMasterData;

use App\Http\Controllers\Controller;
use App\Models\BhpItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminDataBarang extends Controller
{
    /**
     * Tampilkan semua daftar item BHP
     */
    public function index()
    {
        $bhpItems = BhpItem::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data item BHP',
            'data'    => $bhpItems
        ], 200);
    }

    /**
     * Simpan item BHP baru ke database
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bhp'     => ['required', 'string', 'max:255'],
            'tipe_bhp'     => ['required', 'in:satuan,paket'],
            'harga_modal'  => ['required', 'numeric', 'min:0'],
            'tipe_margin'  => ['required', 'in:persen,nominal'],
            'nilai_margin' => ['required', 'numeric', 'min:0'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        // harga_jual otomatis terhitung via boot event pada Model BhpItem
        $bhpItem = BhpItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item BHP berhasil ditambahkan',
            'data'    => $bhpItem
        ], 201);
    }

    /**
     * Tampilkan detail item BHP berdasarkan ID (id_bhp)
     */
    public function show($id)
    {
        $bhpItem = BhpItem::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail item BHP',
            'data'    => $bhpItem
        ], 200);
    }

    /**
     * Update item BHP
     */
    public function update(Request $request, $id)
    {
        $bhpItem = BhpItem::findOrFail($id);

        $validated = $request->validate([
            'nama_bhp'     => ['sometimes', 'required', 'string', 'max:255'],
            'tipe_bhp'     => ['sometimes', 'required', 'in:satuan,paket'],
            'harga_modal'  => ['sometimes', 'required', 'numeric', 'min:0'],
            'tipe_margin'  => ['sometimes', 'required', 'in:persen,nominal'],
            'nilai_margin' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        // harga_jual otomatis terhitung ulang saat save/update
        $bhpItem->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item BHP berhasil diperbarui',
            'data'    => $bhpItem
        ], 200);
    }

    /**
     * Hapus item BHP
     */
    public function destroy($id)
    {
        $bhpItem = BhpItem::findOrFail($id);
        $bhpItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item BHP berhasil dihapus'
        ], 200);
    }

    /**
     * Update margin dan recalculate harga_jual untuk SEMUA item BHP secara global
     */
    public function updateGlobalMargin(Request $request)
    {
        $validated = $request->validate([
            'tipe_margin'  => ['required', 'in:persen,nominal'],
            'nilai_margin' => ['required', 'numeric', 'min:0'],
        ]);

        $tipe  = $validated['tipe_margin'];
        $nilai = $validated['nilai_margin'];

        if ($tipe === 'persen') {
            BhpItem::query()->update([
                'tipe_margin'  => $tipe,
                'nilai_margin' => $nilai,
                'harga_jual'   => DB::raw("harga_modal + (harga_modal * ({$nilai} / 100))"),
            ]);
        } else {
            BhpItem::query()->update([
                'tipe_margin'  => $tipe,
                'nilai_margin' => $nilai,
                'harga_jual'   => DB::raw("harga_modal + {$nilai}"),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Margin dan harga jual semua item BHP berhasil diperbarui secara global',
            'data'    => BhpItem::all()
        ], 200);
    }
}