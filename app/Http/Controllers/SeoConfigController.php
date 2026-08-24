<?php

namespace App\Http\Controllers;

use App\Models\SeoConfig;
use Illuminate\Http\Request;

class SeoConfigController extends Controller
{
    /**
     * Get the SEO configuration
     */
    public function getSeoConfig()
    {
        $config = SeoConfig::first();
        if (!$config) {
            $config = SeoConfig::create([
                'meta_title' => 'Smart Home Care - Layanan Kesehatan Home Care Terpercaya',
                'meta_description' => 'Kami menyediakan layanan kesehatan home care profesional langsung ke rumah Anda.',
                'meta_keywords' => 'homecare, kesehatan, perawat, dokter, fisioterapi',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'meta_title' => $config->meta_title,
                'meta_description' => $config->meta_description,
                'meta_keywords' => $config->meta_keywords,
            ]
        ], 200);
    }

    /**
     * Update the SEO configuration
     */
    public function updateSeoConfig(Request $request)
    {
        $config = SeoConfig::firstOrCreate([]);

        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
        ]);

        $config->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi SEO berhasil diperbarui',
            'data' => [
                'meta_title' => $config->meta_title,
                'meta_description' => $config->meta_description,
                'meta_keywords' => $config->meta_keywords,
            ]
        ], 200);
    }
}
