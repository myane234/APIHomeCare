<?php

namespace App\Http\Controllers;

use App\Models\GlobalConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GlobalConfigController extends Controller
{
    /**
     * Get the global config
     */
    public function getGlobalConfig()
    {
        $config = GlobalConfig::first();
        if (!$config) {
            $config = GlobalConfig::create([
                'app_name' => 'Smart Home Care',
                'meta_title' => 'Smart Home Care - Layanan Kesehatan Home Care Terpercaya',
                'meta_description' => 'Kami menyediakan layanan kesehatan home care profesional langsung ke rumah Anda.',
                'meta_keywords' => 'homecare, kesehatan, perawat, dokter, fisioterapi',
                'whatsapp_number' => '6281234567890',
                'phone_number' => '0211234567',
                'email' => 'info@smarthomecare.com',
                'address' => 'Jl. Kesehatan No. 123, Jakarta Selatan',
                'running_text' => 'Selamat datang di Smart Home Care! Dapatkan potongan harga 10% untuk pemesanan pertama Anda.',
                'maintenance_mode' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => $config->app_name,
                'app_logo' => $config->app_logo_url,
                'app_favicon' => $config->app_favicon_url,
                'meta_title' => $config->meta_title,
                'meta_description' => $config->meta_description,
                'meta_keywords' => $config->meta_keywords,
                'whatsapp_number' => $config->whatsapp_number,
                'phone_number' => $config->phone_number,
                'email' => $config->email,
                'address' => $config->address,
                'socials' => $config->socials ?? [],
                'running_text' => $config->running_text,
                'maintenance_mode' => $config->maintenance_mode,
            ]
        ], 200);
    }

    /**
     * Update the global config
     */
    public function updateGlobalConfig(Request $request)
    {
        $config = GlobalConfig::firstOrCreate([]);

        // Decode stringified JSON from multipart/form-data if necessary
        if (is_string($request->input('socials'))) {
            $decoded = json_decode($request->input('socials'), true);
            if (is_array($decoded)) {
                $request->merge(['socials' => $decoded]);
            }
        }

        $validated = $request->validate([
            'app_name' => 'nullable|string|max:255',
            'app_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'app_favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico,webp|max:1024',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
            'whatsapp_number' => 'nullable|string|max:20',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'socials' => 'nullable|array',
            'socials.*.name' => 'required_with:socials|string|max:255',
            'socials.*.icon' => 'nullable|string|max:255',
            'socials.*.url' => 'nullable|string|max:255',
            'socials.*.text' => 'nullable|string|max:255',
            'running_text' => 'nullable|string',
            'maintenance_mode' => 'nullable', // parsed below
        ]);

        // Handle File Upload for app_logo
        if ($request->hasFile('app_logo')) {
            if ($config->app_logo) {
                Storage::disk('public')->delete($config->app_logo);
            }
            $path = $request->file('app_logo')->store('settings', 'public');
            $validated['app_logo'] = $path;
        }

        // Handle File Upload for app_favicon
        if ($request->hasFile('app_favicon')) {
            if ($config->app_favicon) {
                Storage::disk('public')->delete($config->app_favicon);
            }
            $path = $request->file('app_favicon')->store('settings', 'public');
            $validated['app_favicon'] = $path;
        }

        // Parse maintenance_mode to boolean
        if ($request->has('maintenance_mode')) {
            $validated['maintenance_mode'] = filter_var($request->input('maintenance_mode'), FILTER_VALIDATE_BOOLEAN);
        }

        $config->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi global berhasil diperbarui',
            'data' => [
                'app_name' => $config->app_name,
                'app_logo' => $config->app_logo_url,
                'app_favicon' => $config->app_favicon_url,
                'meta_title' => $config->meta_title,
                'meta_description' => $config->meta_description,
                'meta_keywords' => $config->meta_keywords,
                'whatsapp_number' => $config->whatsapp_number,
                'phone_number' => $config->phone_number,
                'email' => $config->email,
                'address' => $config->address,
                'socials' => $config->socials ?? [],
                'running_text' => $config->running_text,
                'maintenance_mode' => $config->maintenance_mode,
            ]
        ], 200);
    }
}
