<?php

namespace App\Http\Controllers;

use App\Models\MasterBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group Master Bank (Payout Mitra)
 *
 * API untuk mengelola data master bank yang digunakan pada penarikan dana (payout) mitra.
 */
class MasterBankController extends Controller
{
    /**
     * List Semua Bank Aktif (Public)
     *
     * Mengambil daftar seluruh bank yang berstatus aktif untuk ditampilkan pada aplikasi publik / mitra.
     *
     * @unauthenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id_bank": 1,
     *       "nama_bank": "Bank BCA",
     *       "kode_bank": "014",
     *       "gambar": "/storage/uploads/bank/logos/bank_logo_12345.png",
     *       "is_active": true,
     *       "created_by": 1,
     *       "created_at": "2026-01-10T08:00:00.000000Z",
     *       "updated_at": "2026-01-10T08:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function index()
    {
        $banks = MasterBank::where('is_active', true)
            ->orderBy('nama_bank')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $banks
        ]);
    }

    /**
     * List Semua Bank (Admin)
     *
     * Mengambil daftar seluruh bank termasuk yang berstatus tidak aktif.
     *
     * @authenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id_bank": 1,
     *       "nama_bank": "Bank BCA",
     *       "kode_bank": "014",
     *       "gambar": "/storage/uploads/bank/logos/bank_logo_12345.png",
     *       "is_active": false,
     *       "created_by": 1,
     *       "created_at": "2026-01-10T08:00:00.000000Z",
     *       "updated_at": "2026-01-10T08:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function adminIndex()
    {
        $banks = MasterBank::orderBy('nama_bank')->get();

        return response()->json([
            'success' => true,
            'data'    => $banks
        ]);
    }

    /**
     * Tambah Bank Baru (Admin)
     *
     * Menambahkan data bank baru beserta logo bank jika ada.
     *
     * @authenticated
     * 
     * @bodyParam nama_bank string required Nama bank. Example: Bank Mandiri
     * @bodyParam kode_bank string Kode transfer bank. Example: 008
     * @bodyParam gambar file Upload gambar logo bank (Format: jpeg, png, jpg, webp, svg. Max: 1MB).
     * @bodyParam is_active boolean Status keaktifan bank. Example: true
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Bank berhasil ditambahkan.",
     *   "data": {
     *     "id_bank": 2,
     *     "nama_bank": "Bank Mandiri",
     *     "kode_bank": "008",
     *     "gambar": "/storage/uploads/bank/logos/bank_logo_17123_abc.png",
     *     "is_active": true,
     *     "created_by": 1,
     *     "updated_at": "2026-01-10T08:00:00.000000Z",
     *     "created_at": "2026-01-10T08:00:00.000000Z"
     *   }
     * }
     * 
     * @response 422 {
     *   "message": "The nama bank field is required.",
     *   "errors": {
     *     "nama_bank": ["The nama bank field is required."]
     *   }
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bank' => ['required', 'string', 'max:100', 'unique:master_bank,nama_bank'],
            'kode_bank' => ['nullable', 'string', 'max:10'],
            'gambar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:1024'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('gambar')) {
            $file = $request->file('gambar');
            $filename = 'bank_logo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads/bank/logos', $filename, 'public');
            $validated['gambar'] = '/storage/' . $path;
        }

        $validated['created_by'] = $request->user()->id_admin;
        $bank = MasterBank::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank berhasil ditambahkan.',
            'data'    => $bank
        ], 201);
    }

    /**
     * Update Data Bank (Admin)
     *
     * Memperbarui detail bank. Jika mengunggah gambar baru, gambar lama akan otomatis dihapus.
     *
     * @authenticated
     * 
     * @urlParam id integer required ID unik bank. Example: 1
     * 
     * @bodyParam nama_bank string Nama bank. Example: Bank BCA Syariah
     * @bodyParam kode_bank string Kode transfer bank. Example: 536
     * @bodyParam gambar file Upload gambar logo baru.
     * @bodyParam is_active boolean Status keaktifan bank. Example: true
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Data bank berhasil diperbarui.",
     *   "data": {
     *     "id_bank": 1,
     *     "nama_bank": "Bank BCA Syariah",
     *     "kode_bank": "536",
     *     "gambar": "/storage/uploads/bank/logos/bank_logo_17124_xyz.png",
     *     "is_active": true,
     *     "created_by": 1,
     *     "created_at": "2026-01-10T08:00:00.000000Z",
     *     "updated_at": "2026-01-10T09:00:00.000000Z"
     *   }
     * }
     */
    public function update(Request $request, $id)
    {
        $bank = MasterBank::findOrFail($id);

        $validated = $request->validate([
            'nama_bank' => ['sometimes', 'required', 'string', 'max:100', 'unique:master_bank,nama_bank,' . $id . ',id_bank'],
            'kode_bank' => ['nullable', 'string', 'max:10'],
            'gambar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:1024'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('gambar')) {
            if ($bank->gambar) {
                $oldPath = str_replace('/storage/', '', $bank->gambar);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $file = $request->file('gambar');
            $filename = 'bank_logo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads/bank/logos', $filename, 'public');
            $validated['gambar'] = '/storage/' . $path;
        }

        $bank->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data bank berhasil diperbarui.',
            'data'    => $bank->fresh()
        ]);
    }

    /**
     * Toggle Status Aktif Bank (Admin)
     *
     * Mengubah status aktif/non-aktif bank secara instan (flip-flop).
     *
     * @authenticated
     * 
     * @urlParam id integer required ID unik bank. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Status bank berhasil diubah.",
     *   "data": {
     *     "id_bank": 1,
     *     "nama_bank": "Bank BCA",
     *     "is_active": false
     *   }
     * }
     */
    public function toggleStatus($id)
    {
        $bank = MasterBank::findOrFail($id);
        $bank->update(['is_active' => !$bank->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status bank berhasil diubah.',
            'data'    => $bank->fresh()
        ]);
    }

    /**
     * Hapus Bank (Admin)
     *
     * Menghapus bank dari sistem (soft delete jika model mendukung) serta menghapus berkas logo terlampir.
     *
     * @authenticated
     * 
     * @urlParam id integer required ID unik bank. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Bank berhasil dihapus."
     * }
     */
    public function destroy(Request $request, $id)
    {
        $bank = MasterBank::findOrFail($id);

        $bank->update([
            'deleted_by' => $request->user()->id_admin,
        ]);

        if ($bank->gambar) {
            $oldPath = str_replace('/storage/', '', $bank->gambar);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $bank->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank berhasil dihapus.'
        ]);
    }
}