<?php

namespace App\Http\Controllers;

use App\Models\GlobalConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebSettingController extends Controller
{
    /**
     * Get the web setting (Logo and Favicon/Icon)
     */
    public function getWebSetting()
    {
        $config = GlobalConfig::first();
        if (!$config) {
            $config = GlobalConfig::create([
                'app_name' => 'Smart Home Care',
                'whatsapp_number' => '6281234567890',
                'phone_number' => '0211234567',
                'email' => 'info@smarthomecare.com',
                'address' => 'Jl. Kesehatan No. 123, Jakarta Selatan',
                'maintenance_mode' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'app_logo' => $config->app_logo_url,
                'app_favicon' => $config->app_favicon_url,
            ]
        ], 200);
    }

    /**
     * Update the web setting (Logo and Favicon/Icon)
     */
    public function updateWebSetting(Request $request)
    {
        $config = GlobalConfig::firstOrCreate([]);

        $validated = $request->validate([
            'app_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'app_favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico,webp|max:1024',
        ]);

        $updates = [];
        $logDetails = [];

        // Handle File Upload for app_logo
        if ($request->hasFile('app_logo')) {
            if ($config->app_logo) {
                Storage::disk('public')->delete($config->app_logo);
            }
            $path = $request->file('app_logo')->store('settings', 'public');
            $updates['app_logo'] = $path;
            $logDetails[] = 'Logo Web diperbarui';
        }

        // Handle File Upload for app_favicon
        if ($request->hasFile('app_favicon')) {
            if ($config->app_favicon) {
                Storage::disk('public')->delete($config->app_favicon);
            }
            $path = $request->file('app_favicon')->store('settings', 'public');
            $updates['app_favicon'] = $path;
            $logDetails[] = 'Icon Web diperbarui';
        }

        if (!empty($updates)) {
            $config->update($updates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Web setting berhasil diperbarui',
            'data' => [
                'app_logo' => $config->app_logo_url,
                'app_favicon' => $config->app_favicon_url,
            ]
        ], 200);
    }
}
