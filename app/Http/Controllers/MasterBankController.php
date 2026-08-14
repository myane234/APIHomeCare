<?php

namespace App\Http\Controllers;

use App\Models\MasterBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MasterBankController extends Controller
{
    /**
     * List semua bank aktif (public)
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
     * List semua bank (admin, termasuk yg inactive)
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
     * Tambah bank baru (admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bank' => ['required', 'string', 'max:100', 'unique:master_bank,nama_bank'],
            'kode_bank' => ['nullable', 'string', 'max:10'],
            'logo_bank' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:1024'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('logo_bank')) {
            $file = $request->file('logo_bank');
            $filename = 'bank_logo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads/bank/logos', $filename, 'public');
            $validated['logo_bank'] = '/storage/' . $path;
        }

        $bank = MasterBank::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank berhasil ditambahkan.',
            'data'    => $bank
        ], 201);
    }

    /**
     * Update data bank (admin)
     */
    public function update(Request $request, $id)
    {
        $bank = MasterBank::findOrFail($id);

        $validated = $request->validate([
            'nama_bank' => ['sometimes', 'required', 'string', 'max:100', 'unique:master_bank,nama_bank,' . $id . ',id_bank'],
            'kode_bank' => ['nullable', 'string', 'max:10'],
            'logo_bank' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:1024'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('logo_bank')) {
            // Hapus logo lama
            if ($bank->logo_bank) {
                $oldPath = str_replace('/storage/', '', $bank->logo_bank);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $file = $request->file('logo_bank');
            $filename = 'bank_logo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads/bank/logos', $filename, 'public');
            $validated['logo_bank'] = '/storage/' . $path;
        }

        $bank->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data bank berhasil diperbarui.',
            'data'    => $bank->fresh()
        ]);
    }

    /**
     * Toggle status aktif bank (admin)
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
     * Hapus bank (admin)
     */
    public function destroy($id)
    {
        $bank = MasterBank::findOrFail($id);

        if ($bank->logo_bank) {
            $oldPath = str_replace('/storage/', '', $bank->logo_bank);
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
