<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @group CMS Promo
 * Endpoint Untuk Promo Layanan
 */
class PromoController extends Controller
{
    public function index()
    {
        $promos = Promo::with('layanan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $promos,
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_layanan' => ['required', 'exists:master_layanan,id_layanan'],
            'deskripsi' => ['required', 'string'],
            'tipe_diskon' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai_diskon' => [
                'required',
                'numeric',
                'min:0',
                Rule::when($request->input('tipe_diskon') === 'persen', ['max:100']),
            ],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'status_promo' => ['required', 'in:Aktif,Tidak Aktif'],
            'gambar_promo' => ['sometimes','nullable','image','max:2048'],
        ]);

        // create promo without image first
        $promoData = $validated;
        $promo = Promo::create($promoData);

        // Handle image upload separately
        if ($request->hasFile('gambar_promo')) {
            $path = $request->file('gambar_promo')->store('promo_images', 'public');
            $promo->gambar_promo = $path;
            $promo->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Promo layanan berhasil dibuat',
            'data' => $promo->load('layanan'),
        ], 201);
    }

    public function show(Promo $promo)
    {
        $promo->load('layanan');
        
        return response()->json([
            'success' => true,
            'data' => $promo,
        ], 200);
    }

    public function update(Request $request, Promo $promo)
    {
        $validated = $request->validate([
            'id_layanan' => ['sometimes', 'required', 'exists:master_layanan,id_layanan'],
            'deskripsi' => ['sometimes', 'required', 'string'],
            'tipe_diskon' => ['sometimes', Rule::in(['persen', 'nominal'])],
            'nilai_diskon' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                Rule::when(($request->input('tipe_diskon') ?? $promo->tipe_diskon) === 'persen', ['max:100']),
            ],
            'tanggal_mulai' => ['sometimes', 'required', 'date'],
            'tanggal_berakhir' => ['sometimes', 'required', 'date', 'after_or_equal:tanggal_mulai'],
            'status_promo' => ['sometimes', 'required', 'in:Aktif,Tidak Aktif'],
            'gambar_promo' => ['sometimes','nullable','image','max:2048'],
        ]);

        // Handle file upload: delete old file if replaced
        if ($request->hasFile('gambar_promo')) {
            if ($promo->gambar_promo) {
                Storage::disk('public')->delete($promo->gambar_promo);
            }
            $path = $request->file('gambar_promo')->store('promo_images', 'public');
            $validated['gambar_promo'] = $path;
        }

        $promo->fill($validated);
        $promo->save();

        return response()->json([
            'success' => true,
            'message' => 'Promo layanan berhasil diubah',
            'data' => $promo->load('layanan'),
        ], 200);
    }

    public function destroy(Promo $promo)
    {
        if ($promo->gambar_promo) {
            Storage::disk('public')->delete($promo->gambar_promo);
        }
        $promo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paket bundling berhasil dihapus',
        ], 200);
    }

    public function getActivePromos()
    {
        $promos = Promo::with('layanan')
            ->where('status_promo', 'Aktif')
            ->whereDate('tanggal_mulai', '<=', now()->toDateString())
            ->whereDate('tanggal_berakhir', '>=', now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $promos,
        ], 200);
    }
}