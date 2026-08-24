<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class KonfigurasiEnvController extends Controller
{
    /**
     * Get all current .env settings
     */
    public function index()
    {
        $envData = $this->parseEnvFile();

        return response()->json([
            'status' => 'success',
            'data'   => $envData
        ], 200);
    }

    /**
     * Update .env key-value pairs
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
            $value = $value ?? '';

            // Handle spasi, karakter khusus (#, $, dll), atau regex CORS
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

        // Hapus & refresh cache konfigurasi Laravel
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
                $env[trim($key)] = trim($value, "\"'\r\t\n");
            }
        }

        return $env;
    }
}