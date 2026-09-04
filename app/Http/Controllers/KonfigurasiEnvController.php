<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class KonfigurasiEnvController extends Controller
{
    /**
     * Whitelist key yang DIIZINKAN untuk di-edit via CMS.
     * Key selain di daftar ini otomatis is_editable = false.
     */
    private array $editableKeys = [
        'APP_NAME',
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_SECRET',
        'GOOGLE_REDIRECT_URI',
        'SANCTUM_STATEFUL_DOMAINS',
        'CORS_ALLOWED_ORIGINS',
        'MIDTRANS_SERVER_KEY',
        'MIDTRANS_CLIENT_KEY',
        'MIDTRANS_MERCHANT_ID',
        'MIDTRANS_IS_PRODUCTION',
        'FEE_MIDTRANS',
        'MIDTRANS_EXPIRY_DURATION',
        'MIDTRANS_EXPIRY_UNIT',
        'MIDTRANS_NOTIFICATION_URL',
        'MIDTRANS_FINISH_URL',
        'MIDTRANS_UNFINISH_URL',
        'MIDTRANS_ERROR_URL',
        'MIDTRANS_AUTO_SETTLEMENT',
    ];

    /**
     * Get semua key .env beserta status is_editable
     */
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->parseEnvFile()
        ], 200);
    }

    /**
     * Update .env (Hanya memproses key dengan is_editable = true)
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'APP_NAME'               => 'sometimes|required|string',
            'APP_ENV'                => 'sometimes|required|in:local,production,staging',
            'APP_DEBUG'              => 'sometimes|required|in:true,false',
            'APP_URL'                => 'sometimes|required|url',
            'DB_HOST'                => 'sometimes|required|string',
            'DB_PORT'                => 'sometimes|required|numeric',
            'DB_DATABASE'            => 'sometimes|required|string',
            'DB_USERNAME'            => 'sometimes|required|string',
            'DB_PASSWORD'            => 'nullable|string',
            'GOOGLE_CLIENT_ID'       => 'nullable|string',
            'GOOGLE_CLIENT_SECRET'   => 'nullable|string',
            'GOOGLE_REDIRECT_URI'   => 'nullable|url',
            'SANCTUM_STATEFUL_DOMAINS' => 'nullable|string',
            'CORS_ALLOWED_ORIGINS'   => 'nullable|string',
            'MIDTRANS_SERVER_KEY'    => 'nullable|string',
            'MIDTRANS_CLIENT_KEY'    => 'nullable|string',
            'MIDTRANS_MERCHANT_ID'   => 'nullable|string',
            'MIDTRANS_IS_PRODUCTION' => 'nullable|in:true,false',
            'FEE_MIDTRANS'           => 'nullable|numeric',
            'MIDTRANS_EXPIRY_DURATION' => 'nullable|integer|min:1',
            'MIDTRANS_EXPIRY_UNIT'     => 'nullable|in:minutes,hours,days',
            'MIDTRANS_NOTIFICATION_URL'=> 'nullable|url',
            'MIDTRANS_FINISH_URL'      => 'nullable|url',
            'MIDTRANS_UNFINISH_URL'    => 'nullable|url',
            'MIDTRANS_ERROR_URL'       => 'nullable|url',
            'MIDTRANS_AUTO_SETTLEMENT' => 'nullable|in:true,false',
        ]);

        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'File .env tidak ditemukan.'
            ], 404);
        }

        $envContent = File::get($envPath);

        foreach ($validated as $key => $value) {
            // Abaikan jika key tidak terdaftar di whitelist
            if (!in_array($key, $this->editableKeys)) {
                continue;
            }

            $value = $value ?? '';

            if (preg_match('/[\s#$"\\\\]/', $value) || str_contains($value, '=')) {
                $value = '"' . addcslashes($value, '"$') . '"';
            }

            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        File::put($envPath, $envContent);
        Artisan::call('config:clear');

        return response()->json([
            'status'  => 'success',
            'message' => 'Konfigurasi .env berhasil diperbarui.'
        ], 200);
    }

    private function parseEnvFile(): array
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) return [];

        $lines = explode("\n", File::get($envPath));
        $env = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);

                $env[] = [
                    'key'         => $key,
                    'value'       => trim($value, "\"'\r\t\n"),
                    'is_editable' => in_array($key, $this->editableKeys),
                ];
            }
        }

        return $env;
    }
}