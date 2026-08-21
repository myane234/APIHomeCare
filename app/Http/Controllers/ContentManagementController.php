<?php

namespace App\Http\Controllers;

use App\Models\ContentManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContentManagementController extends Controller
{
    /**
     * Get Home Page Content
     */
    public function getHome()
    {
        $content = ContentManagement::firstOrCreate([]);

        // Fetch active promos
        $promos = \App\Models\Promo::with('layanans')
            ->where('status_promo', 'Aktif')
            ->whereDate('tanggal_mulai', '<=', now()->toDateString())
            ->whereDate('tanggal_berakhir', '>=', now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch articles
        $artikels = \App\Models\Artikel::with('kategori')
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch article categories
        $kategoriArtikel = \App\Models\KategoriArtikel::all();

        // Fetch services
        $layanans = \App\Models\MasterLayanan::with('kategori')
            ->get()
            ->map(function ($item) {
                if ($item->foto_layanan && !str_starts_with($item->foto_layanan, 'http')) {
                    $item->foto_layanan = url(Storage::url($item->foto_layanan));
                }
                $item->kategori_layanan = $item->kategori ? $item->kategori->nama_kategori : null;
                return $item;
            });

        return response()->json([
            'home_banner' => $content->home_banner_url,
            'home_text_banner' => $content->home_text_banner,
            'home_description' => $content->home_description,
            
            'promo_heading' => $content->promo_heading,
            'promo_text' => $content->promo_text,
            'promos' => $promos,

            'artikel_heading' => $content->artikel_heading,
            'artikel_text' => $content->artikel_text,
            'artikels' => $artikels,
            'kategori_artikel' => $kategoriArtikel,

            'layanan_heading' => $content->layanan_heading,
            'layanan_text' => $content->layanan_text,
            'layanans' => $layanans,
        ], 200);
    }

    /**
     * Get Tentang Kami Page Content
     */
    public function getAbout()
    {
        $content = ContentManagement::firstOrCreate([]);

        return response()->json([
            'about_banner' => $content->about_banner_url,
            'about_text_banner' => $content->about_text_banner,
            'about_description_text' => $content->about_description_text,
            'about_description_image' => $content->about_description_image_url,
            'visi_misi' => $content->visi_misi,
            'cara_kerja' => $content->cara_kerja,
            'wilayah_layanan' => $content->wilayah_layanan,
            'komitmen' => $content->komitmen,
        ], 200);
    }

    /**
     * Update Home Page Content (Admin)
     */
    public function updateHome(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $validated = $request->validate([
            'home_banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'home_text_banner' => ['nullable', 'string'],
            'home_description' => ['nullable', 'string'],
            'promo_heading' => ['nullable', 'string', 'max:255'],
            'promo_text' => ['nullable', 'string'],
            'artikel_heading' => ['nullable', 'string', 'max:255'],
            'artikel_text' => ['nullable', 'string'],
            'layanan_heading' => ['nullable', 'string', 'max:255'],
            'layanan_text' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('home_banner')) {
            // Delete old banner if exists
            if ($content->home_banner) {
                Storage::disk('public')->delete($content->home_banner);
            }
            $path = $request->file('home_banner')->store('content', 'public');
            $validated['home_banner'] = $path;
        }

        $content->update($validated);

        return response()->json([
            'message' => 'Konten Home berhasil diperbarui',
            'data' => [
                'home_banner' => $content->home_banner_url,
                'home_text_banner' => $content->home_text_banner,
                'home_description' => $content->home_description,
                'promo_heading' => $content->promo_heading,
                'promo_text' => $content->promo_text,
                'artikel_heading' => $content->artikel_heading,
                'artikel_text' => $content->artikel_text,
                'layanan_heading' => $content->layanan_heading,
                'layanan_text' => $content->layanan_text,
            ]
        ], 200);
    }

    /**
     * Update Tentang Kami Page Content (Admin)
     */
    public function updateAbout(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $validated = $request->validate([
            'about_banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'about_text_banner' => ['nullable', 'string'],
            'about_description_text' => ['nullable', 'string'],
            'about_description_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'visi_misi' => ['nullable', 'string'],
            'cara_kerja' => ['nullable', 'string'],
            'wilayah_layanan' => ['nullable', 'string'],
            'komitmen' => ['nullable', 'string'],
        ]);

        // Handle about_banner upload
        if ($request->hasFile('about_banner')) {
            if ($content->about_banner) {
                Storage::disk('public')->delete($content->about_banner);
            }
            $path = $request->file('about_banner')->store('content', 'public');
            $validated['about_banner'] = $path;
        }

        // Handle about_description_image upload
        if ($request->hasFile('about_description_image')) {
            if ($content->about_description_image) {
                Storage::disk('public')->delete($content->about_description_image);
            }
            $path = $request->file('about_description_image')->store('content', 'public');
            $validated['about_description_image'] = $path;
        }

        $content->update($validated);

        return response()->json([
            'message' => 'Konten Tentang Kami berhasil diperbarui',
            'data' => [
                'about_banner' => $content->about_banner_url,
                'about_text_banner' => $content->about_text_banner,
                'about_description_text' => $content->about_description_text,
                'about_description_image' => $content->about_description_image_url,
                'visi_misi' => $content->visi_misi,
                'cara_kerja' => $content->cara_kerja,
                'wilayah_layanan' => $content->wilayah_layanan,
                'komitmen' => $content->komitmen,
            ]
        ], 200);
    }

    /**
     * Get Gabung Mitra Content
     */
    public function getMitra()
    {
        $content = ContentManagement::firstOrCreate([]);

        return response()->json([
            'mitra_banner' => $content->mitra_banner_url,
            'mitra_text_banner' => $content->mitra_text_banner,
            'mitra_description' => $content->mitra_description,
        ], 200);
    }

    /**
     * Update Gabung Mitra Content (Admin)
     */
    public function updateMitra(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $validated = $request->validate([
            'mitra_banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'mitra_text_banner' => ['nullable', 'string'],
            'mitra_description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('mitra_banner')) {
            if ($content->mitra_banner) {
                Storage::disk('public')->delete($content->mitra_banner);
            }
            $path = $request->file('mitra_banner')->store('content', 'public');
            $validated['mitra_banner'] = $path;
        }

        $content->update($validated);

        return response()->json([
            'message' => 'Konten Gabung Mitra berhasil diperbarui',
            'data' => [
                'mitra_banner' => $content->mitra_banner_url,
                'mitra_text_banner' => $content->mitra_text_banner,
                'mitra_description' => $content->mitra_description,
            ]
        ], 200);
    }

    /**
     * Get Footer Content
     */
    public function getFooter()
    {
        $content = ContentManagement::firstOrCreate([]);

        return response()->json([
            'footer_description' => $content->footer_description,
            'footer_phone' => $content->footer_phone,
            'footer_email' => $content->footer_email,
            'footer_address' => $content->footer_address,
            'footer_socials' => $content->footer_socials ?? [],
        ], 200);
    }

    /**
     * Update Footer Content (Admin)
     */
    public function updateFooter(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $validated = $request->validate([
            'footer_description' => ['nullable', 'string'],
            'footer_phone' => ['nullable', 'string', 'max:50'],
            'footer_email' => ['nullable', 'email', 'max:255'],
            'footer_address' => ['nullable', 'string'],
            'footer_socials' => ['nullable', 'array'],
            'footer_socials.*.name' => ['required_with:footer_socials', 'string', 'max:255'],
            'footer_socials.*.icon' => ['nullable', 'string', 'max:255'],
            'footer_socials.*.url' => ['nullable', 'string', 'max:255'],
            'footer_socials.*.text' => ['nullable', 'string', 'max:255'],
        ]);

        $content->update($validated);

        return response()->json([
            'message' => 'Konten Footer berhasil diperbarui',
            'data' => [
                'footer_description' => $content->footer_description,
                'footer_phone' => $content->footer_phone,
                'footer_email' => $content->footer_email,
                'footer_address' => $content->footer_address,
                'footer_socials' => $content->footer_socials ?? [],
            ]
        ], 200);
    }
}
