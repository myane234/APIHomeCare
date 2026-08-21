<?php

namespace App\Http\Controllers;

use App\Jobs\WilayahImportJob;
use App\Models\WilayahImportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Services\WilayahImportService;
use Throwable;

/**
 * @group Seeder API
 */
class AdminSeederController extends Controller
{
    private const SEEDERS = [
        'roleSeeder' => [
            'class' => 'Database\\Seeders\\roleSeeder',
            'label' => 'Role',
            'description' => 'Data role pengguna.',
        ],
        'AdminTierSeeder' => [
            'class' => 'Database\\Seeders\\AdminTierSeeder',
            'label' => 'Admin Tier',
            'description' => 'Data tier dan permission admin.',
        ],
        'AdminSeeder' => [
            'class' => 'Database\\Seeders\\AdminSeeder',
            'label' => 'Admin',
            'description' => 'Akun admin default.',
        ],
        'SuperAdminSeeder' => [
            'class' => 'Database\\Seeders\\SuperAdminSeeder',
            'label' => 'Super Admin',
            'description' => 'Akun super admin default.',
        ],
        'KategoriLayananSeeder' => [
            'class' => 'Database\\Seeders\\KategoriLayananSeeder',
            'label' => 'Kategori Layanan',
            'description' => 'Kategori layanan home care.',
        ],
        'MasterBankSeeder' => [
            'class' => 'Database\\Seeders\\MasterBankSeeder',
            'label' => 'Master Bank Payout',
            'description' => 'Daftar bank untuk payout mitra.',
        ],
        'ProvinsiSeeder' => [
            'class' => 'Database\\Seeders\\ProvinsiSeeder',
            'label' => 'Provinsi',
            'description' => 'Data provinsi dari API wilayah Indonesia.',
        ],
        'KotaKabupatenSeeder' => [
            'class' => 'Database\\Seeders\\KotaKabupatenSeeder',
            'label' => 'Kota/Kabupaten',
            'description' => 'Data kota dan kabupaten.',
        ],
        'WilayahSeeder' => [
            'class' => 'Database\\Seeders\\WilayahSeeder',
            'label' => 'Wilayah Layanan',
            'description' => 'Data wilayah layanan.',
        ],
        'MasterProvinsiSeeder' => [
            'class' => 'Database\\Seeders\\MasterProvinsiSeeder',
            'label' => 'Master Provinsi',
            'description' => 'Sinkronisasi master provinsi dari API.',
        ],
        'PromoSeeder' => [
            'class' => 'Database\\Seeders\\PromoSeeder',
            'label' => 'Promo',
            'description' => 'Data promo.',
        ],
        'LayananSeeder' => [
            'class' => 'Database\\Seeders\\LayananSeeder',
            'label' => 'Layanan',
            'description' => 'Data katalog layanan.',
        ],
        'ArtikelSeeder' => [
            'class' => 'Database\\Seeders\\ArtikelSeeder',
            'label' => 'Artikel',
            'description' => 'Data artikel.',
        ],
        'TenagaMedisSeeder' => [
            'class' => 'Database\\Seeders\\TenagaMedisSeeder',
            'label' => 'Tenaga Medis',
            'description' => 'Data tenaga medis.',
        ],
    ];
    
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => collect(self::SEEDERS)->map(
                fn (array $seeder, string $name) => [
                    'name' => $name,
                    'label' => $seeder['label'],
                    'description' => $seeder['description'],
                ]
            )->values(),
            'all' => [
                'name' => 'all',
                'label' => 'Semua Seeder',
                'description' => 'Menjalankan DatabaseSeeder, termasuk factory data pasien.',
            ],
        ]);
    }

    public function run(Request $request)
    {
        $validated = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'seeders' => ['sometimes', 'array', 'min:1'],
            'seeders.*' => ['string', 'distinct', Rule::in(array_keys(self::SEEDERS))],
        ]);

        $runAll = (bool) ($validated['all'] ?? false);
        $selected = $validated['seeders'] ?? [];

        if (!$runAll && $selected === []) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu seeder atau kirim all=true.',
            ], 422);
        }

        if (!$runAll && $selected === ['WilayahSeeder']) {
            return $this->startWilayahImport();
        }

        $seeders = $runAll
            ? [['name' => 'all', 'class' => 'Database\\Seeders\\DatabaseSeeder']]
            : collect($selected)->map(fn (string $name) => [
                'name' => $name,
                'class' => self::SEEDERS[$name]['class'],
            ])->all();

        $results = [];

        try {
            foreach ($seeders as $seeder) {
                $exitCode = Artisan::call('db:seed', [
                    '--class' => $seeder['class'],
                    '--force' => true,
                ]);

                $results[] = [
                    'name' => $seeder['name'],
                    'status' => $exitCode === 0 ? 'success' : 'failed',
                    'output' => trim(Artisan::output()),
                ];

                if ($exitCode !== 0) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            Log::error('Admin gagal menjalankan seeder.', [
                'admin_id' => $request->user()?->id_admin,
                'seeders' => $seeders,
                'exception' => $exception,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Seeder gagal dijalankan.',
                'results' => $results,
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ], 500);
        }

        $failed = collect($results)->contains('status', 'failed');

        return response()->json([
            'success' => !$failed,
            'message' => $failed ? 'Sebagian seeder gagal dijalankan.' : 'Seeder berhasil dijalankan.',
            'results' => $results,
        ], $failed ? 500 : 200);
    }

    public function startWilayahImport()
    {
        $activeRun = WilayahImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest()
            ->first();

        if ($activeRun) {
            return response()->json([
                'success' => false,
                'message' => 'Import wilayah sedang berjalan.',
                'data' => $activeRun,
            ], 409);
        }

        $run = WilayahImportRun::create();
        WilayahImportJob::dispatch($run->id)->onQueue('wilayah');

        return response()->json([
            'success' => true,
            'message' => 'Import wilayah dimasukkan ke antrean.',
            'data' => $run,
        ], 202);
    }

    public function wilayahImportStatus(string $runId)
    {
        $run = WilayahImportRun::with('logs')->findOrFail($runId);

        return response()->json([
            'success' => true,
            'data' => $run,
        ]);
    }

    public function latestWilayahImportStatus()
    {
        $run = WilayahImportRun::with('logs')->latest()->first();

        return response()->json([
            'success' => true,
            'data' => $run,
        ]);
    }

    public function wilayahSource(WilayahImportService $service)
    {
        return response()->json([
            'success' => true,
            'data' => $service->getSource(),
        ]);
    }

    public function saveWilayahApi(Request $request, WilayahImportService $service)
    {
        $endpointUrlRule = function (string $attribute, mixed $value, \Closure $fail): void {
            $urlWithoutPlaceholder = preg_replace('/\{[^}]+\}/', 'placeholder', $value);

            if (!filter_var($urlWithoutPlaceholder, FILTER_VALIDATE_URL)) {
                $fail("{$attribute} harus berupa URL yang valid.");
            }
        };

        $validated = $request->validate([
            'provinces_url' => ['required', 'max:2000', $endpointUrlRule],
            'regencies_url' => ['required', 'max:2000', $endpointUrlRule],
            'districts_url' => ['required', 'max:2000', $endpointUrlRule],
            'villages_url' => ['required', 'max:2000', $endpointUrlRule],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sumber API wilayah berhasil disimpan.',
            'data' => $service->saveApi($validated),
        ]);
    }

    public function saveWilayahFile(Request $request, WilayahImportService $service)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,json,xlsx', 'max:20480'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File sumber wilayah berhasil disimpan.',
            'data' => $service->saveFile($validated['file']),
        ]);
    }
}