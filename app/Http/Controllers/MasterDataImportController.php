<?php

namespace App\Http\Controllers;

use App\Models\BhpItem;
use App\Models\MasterAgama;
use App\Models\MasterBank;
use App\Models\MasterPendidikan;
use App\Models\MasterTarifTransport;
use App\Models\MasterUniversitas;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * MasterDataImportController
 *
 * Menangani import data master via file Excel (.xlsx / .csv)
 * dan download template Excel untuk masing-masing master data.
 *
 * Master data yang didukung:
 *  - Bank            : POST /master-data/import/bank            | GET /master-data/template/bank
 *  - Universitas     : POST /master-data/import/universitas     | GET /master-data/template/universitas
 *  - Agama           : POST /master-data/import/agama           | GET /master-data/template/agama
 *  - Pendidikan      : POST /master-data/import/pendidikan      | GET /master-data/template/pendidikan
 *  - BHP Items       : POST /master-data/import/bhp             | GET /master-data/template/bhp
 *  - Tarif Transport : POST /master-data/import/tarif-transport | GET /master-data/template/tarif-transport
 *
 * @group Master Data - Import Excel
 */
class MasterDataImportController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    // DOWNLOAD TEMPLATE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Download Template Excel - Bank
     *
     * Mengunduh file Excel template untuk import data master bank.
     * Kolom: nama_bank (wajib), kode_bank (opsional), is_active (opsional: 1/0, default 1)
     *
     * @authenticated
     */
    public function templateBank()
    {
        $rows = collect([
            ['nama_bank' => 'Bank Central Asia (BCA)', 'kode_bank' => 'BCA', 'is_active' => 1],
            ['nama_bank' => 'Bank Negara Indonesia (BNI)', 'kode_bank' => 'BNI', 'is_active' => 1],
            ['nama_bank' => 'Bank Mandiri', 'kode_bank' => 'MANDIRI', 'is_active' => 1],
        ]);

        return $this->streamExcel($rows, 'template_bank.xlsx');
    }

    /**
     * Download Template Excel - Universitas
     *
     * Mengunduh file Excel template untuk import data master universitas.
     * Kolom: nama_universitas (wajib), is_active (opsional: 1/0, default 1)
     *
     * @authenticated
     */
    public function templateUniversitas()
    {
        $rows = collect([
            ['nama_universitas' => 'Universitas Indonesia', 'is_active' => 1],
            ['nama_universitas' => 'Institut Teknologi Bandung', 'is_active' => 1],
            ['nama_universitas' => 'Universitas Gadjah Mada', 'is_active' => 1],
        ]);

        return $this->streamExcel($rows, 'template_universitas.xlsx');
    }

    /**
     * Download Template Excel - Agama
     *
     * Mengunduh file Excel template untuk import data master agama.
     * Kolom: nama_agama (wajib)
     *
     * @authenticated
     */
    public function templateAgama()
    {
        $rows = collect([
            ['nama_agama' => 'Islam'],
            ['nama_agama' => 'Kristen Protestan'],
            ['nama_agama' => 'Kristen Katolik'],
        ]);

        return $this->streamExcel($rows, 'template_agama.xlsx');
    }

    /**
     * Download Template Excel - Pendidikan
     *
     * Mengunduh file Excel template untuk import data master pendidikan.
     * Kolom: nama_pendidikan (wajib)
     *
     * @authenticated
     */
    public function templatePendidikan()
    {
        $rows = collect([
            ['nama_pendidikan' => 'D3 Keperawatan'],
            ['nama_pendidikan' => 'S1 Keperawatan'],
            ['nama_pendidikan' => 'S1 Kedokteran'],
        ]);

        return $this->streamExcel($rows, 'template_pendidikan.xlsx');
    }

    /**
     * Download Template Excel - BHP Items
     *
     * Mengunduh file Excel template untuk import data master BHP (Bahan Habis Pakai).
     * Kolom: nama_bhp (wajib), harga_modal (wajib), tipe_margin (wajib: persen/nominal),
     *        nilai_margin (wajib), is_active (opsional: 1/0, default 1)
     *
     * @authenticated
     */
    public function templateBhp()
    {
        $rows = collect([
            [
                'nama_bhp'     => 'Kasa Steril',
                'harga_modal'  => 5000,
                'tipe_margin'  => 'persen',
                'nilai_margin' => 20,
                'is_active'    => 1,
            ],
            [
                'nama_bhp'     => 'Handscoon',
                'harga_modal'  => 3000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 500,
                'is_active'    => 1,
            ],
            [
                'nama_bhp'     => 'Alkohol Swab',
                'harga_modal'  => 1000,
                'tipe_margin'  => 'persen',
                'nilai_margin' => 30,
                'is_active'    => 1,
            ],
        ]);

        return $this->streamExcel($rows, 'template_bhp.xlsx');
    }

    /**
     * Download Template Excel - Tarif Transport
     *
     * Mengunduh file Excel template untuk import data master tarif transport.
     * Kolom: tarif_per_10_km (wajib, nominal Rupiah per 10 km)
     *
     * @authenticated
     */
    public function templateTarifTransport()
    {
        $rows = collect([
            ['tarif_per_10_km' => 10000],
            ['tarif_per_10_km' => 15000],
        ]);

        return $this->streamExcel($rows, 'template_tarif_transport.xlsx');
    }

    // ──────────────────────────────────────────────────────────────────────
    // IMPORT
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Import Excel - Bank
     *
     * Mengimpor data master bank dari file Excel. Data yang sudah ada (berdasarkan
     * kode_bank) akan di-update, data baru akan di-insert.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel (.xlsx atau .csv). Max: 5MB.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Import bank selesai. 3 berhasil, 0 gagal.",
     *   "summary": { "total": 3, "berhasil": 3, "diperbarui": 1, "ditambah": 2, "gagal": 0 },
     *   "errors": []
     * }
     */
    public function importBank(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $adminId = $request->user()->id_admin ?? null;

        $result = $this->processImport(
            file: $request->file('file'),
            rowHandler: function (array $row, int $lineNo) use ($adminId): array {
                $validator = Validator::make($row, [
                    'nama_bank' => 'required|string|max:100',
                    'kode_bank' => 'nullable|string|max:10',
                    'is_active' => 'nullable|in:0,1,true,false',
                ]);

                if ($validator->fails()) {
                    return ['error' => 'Baris ' . $lineNo . ': ' . implode(', ', $validator->errors()->all())];
                }

                $kodeBank  = strtoupper(trim($row['kode_bank'] ?? '')) ?: null;
                $isActive  = isset($row['is_active']) ? (bool) (int) $row['is_active'] : true;
                $namaBank  = trim($row['nama_bank']);

                // Upsert berdasarkan kode_bank (jika ada) atau nama_bank
                $existing = $kodeBank
                    ? MasterBank::where('kode_bank', $kodeBank)->first()
                    : MasterBank::where('nama_bank', $namaBank)->first();

                if ($existing) {
                    $existing->update([
                        'nama_bank' => $namaBank,
                        'kode_bank' => $kodeBank,
                        'is_active' => $isActive,
                    ]);
                    return ['updated' => true];
                }

                MasterBank::create([
                    'nama_bank'  => $namaBank,
                    'kode_bank'  => $kodeBank,
                    'is_active'  => $isActive,
                    'created_by' => $adminId,
                ]);
                return ['created' => true];
            }
        );

        return $this->importResponse('bank', $result);
    }

    /**
     * Import Excel - Universitas
     *
     * Mengimpor data master universitas dari file Excel.
     * Data yang sudah ada (berdasarkan nama_universitas) akan di-update.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel (.xlsx atau .csv). Max: 5MB.
     */
    public function importUniversitas(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $result = $this->processImport(
            file: $request->file('file'),
            rowHandler: function (array $row, int $lineNo): array {
                $validator = Validator::make($row, [
                    'nama_universitas' => 'required|string|max:255',
                    'is_active'        => 'nullable|in:0,1,true,false',
                ]);

                if ($validator->fails()) {
                    return ['error' => 'Baris ' . $lineNo . ': ' . implode(', ', $validator->errors()->all())];
                }

                $nama     = trim($row['nama_universitas']);
                $isActive = isset($row['is_active']) ? (bool) (int) $row['is_active'] : true;

                $existing = MasterUniversitas::where('nama_universitas', $nama)->first();

                if ($existing) {
                    $existing->update(['is_active' => $isActive]);
                    return ['updated' => true];
                }

                MasterUniversitas::create(['nama_universitas' => $nama, 'is_active' => $isActive]);
                return ['created' => true];
            }
        );

        return $this->importResponse('universitas', $result);
    }

    /**
     * Import Excel - Agama
     *
     * Mengimpor data master agama dari file Excel.
     * Data yang sudah ada (berdasarkan nama_agama) dilewati.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel (.xlsx atau .csv). Max: 5MB.
     */
    public function importAgama(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $result = $this->processImport(
            file: $request->file('file'),
            rowHandler: function (array $row, int $lineNo): array {
                $validator = Validator::make($row, [
                    'nama_agama' => 'required|string|max:100',
                ]);

                if ($validator->fails()) {
                    return ['error' => 'Baris ' . $lineNo . ': ' . implode(', ', $validator->errors()->all())];
                }

                $nama = trim($row['nama_agama']);

                if (MasterAgama::where('nama_agama', $nama)->exists()) {
                    return ['skipped' => true]; // sudah ada, lewati
                }

                MasterAgama::create(['nama_agama' => $nama]);
                return ['created' => true];
            }
        );

        return $this->importResponse('agama', $result);
    }

    /**
     * Import Excel - Pendidikan
     *
     * Mengimpor data master pendidikan dari file Excel.
     * Data yang sudah ada (berdasarkan nama_pendidikan) dilewati.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel (.xlsx atau .csv). Max: 5MB.
     */
    public function importPendidikan(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $result = $this->processImport(
            file: $request->file('file'),
            rowHandler: function (array $row, int $lineNo): array {
                $validator = Validator::make($row, [
                    'nama_pendidikan' => 'required|string|max:100',
                ]);

                if ($validator->fails()) {
                    return ['error' => 'Baris ' . $lineNo . ': ' . implode(', ', $validator->errors()->all())];
                }

                $nama = trim($row['nama_pendidikan']);

                if (MasterPendidikan::where('nama_pendidikan', $nama)->exists()) {
                    return ['skipped' => true];
                }

                MasterPendidikan::create(['nama_pendidikan' => $nama]);
                return ['created' => true];
            }
        );

        return $this->importResponse('pendidikan', $result);
    }

    /**
     * Import Excel - BHP Items
     *
     * Mengimpor data master BHP dari file Excel.
     * Data yang sudah ada (berdasarkan nama_bhp) akan di-update.
     * Harga jual dihitung otomatis dari harga_modal + margin.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel (.xlsx atau .csv). Max: 5MB.
     */
    public function importBhp(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $result = $this->processImport(
            file: $request->file('file'),
            rowHandler: function (array $row, int $lineNo): array {
                $validator = Validator::make($row, [
                    'nama_bhp'     => 'required|string|max:255',
                    'harga_modal'  => 'required|numeric|min:0',
                    'tipe_margin'  => 'required|in:persen,nominal',
                    'nilai_margin' => 'required|numeric|min:0',
                    'is_active'    => 'nullable|in:0,1,true,false',
                ]);

                if ($validator->fails()) {
                    return ['error' => 'Baris ' . $lineNo . ': ' . implode(', ', $validator->errors()->all())];
                }

                $nama      = trim($row['nama_bhp']);
                $isActive  = isset($row['is_active']) ? (bool) (int) $row['is_active'] : true;

                $data = [
                    'harga_modal'  => (float) $row['harga_modal'],
                    'tipe_margin'  => $row['tipe_margin'],
                    'nilai_margin' => (float) $row['nilai_margin'],
                    'is_active'    => $isActive,
                    // harga_jual dihitung otomatis oleh BhpItem::boot() saving hook
                ];

                $existing = BhpItem::where('nama_bhp', $nama)->first();

                if ($existing) {
                    $existing->update($data);
                    return ['updated' => true];
                }

                BhpItem::create(array_merge($data, ['nama_bhp' => $nama]));
                return ['created' => true];
            }
        );

        return $this->importResponse('BHP', $result);
    }

    /**
     * Import Excel - Tarif Transport
     *
     * Mengimpor data master tarif transport dari file Excel.
     * Setiap baris adalah satu entri tarif (tarif_per_10_km).
     * Data yang sudah ada dengan nilai sama dilewati.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel (.xlsx atau .csv). Max: 5MB.
     */
    public function importTarifTransport(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $result = $this->processImport(
            file: $request->file('file'),
            rowHandler: function (array $row, int $lineNo): array {
                $validator = Validator::make($row, [
                    'tarif_per_10_km' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    return ['error' => 'Baris ' . $lineNo . ': ' . implode(', ', $validator->errors()->all())];
                }

                $tarif = (float) $row['tarif_per_10_km'];

                if (MasterTarifTransport::where('tarif_per_10_km', $tarif)->exists()) {
                    return ['skipped' => true];
                }

                MasterTarifTransport::create(['tarif_per_10_km' => $tarif]);
                return ['created' => true];
            }
        );

        return $this->importResponse('tarif transport', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Proses import file Excel secara umum.
     * Setiap baris diproses oleh $rowHandler yang mengembalikan:
     *   - ['created' => true]  = data baru berhasil dibuat
     *   - ['updated' => true]  = data lama berhasil diperbarui
     *   - ['skipped' => true]  = data sudah ada, dilewati
     *   - ['error' => string]  = validasi atau error lain
     *
     * @param  \Illuminate\Http\UploadedFile $file
     * @param  callable $rowHandler function(array $row, int $lineNo): array
     * @return array{berhasil: int, ditambah: int, diperbarui: int, dilewati: int, gagal: int, errors: array}
     */
    private function processImport($file, callable $rowHandler): array
    {
        $stats = [
            'berhasil'   => 0,
            'ditambah'   => 0,
            'diperbarui' => 0,
            'dilewati'   => 0,
            'gagal'      => 0,
            'errors'     => [],
        ];

        $lineNo = 1; // baris pertama adalah header, data mulai baris ke-2

        try {
            (new FastExcel)->import($file->getRealPath(), function ($row) use (&$stats, &$lineNo, $rowHandler) {
                $lineNo++;

                // Normalisasi key: trim whitespace dan lowercase
                $row = collect($row)
                    ->mapWithKeys(fn($v, $k) => [strtolower(trim((string) $k)) => is_string($v) ? trim($v) : $v])
                    ->toArray();

                // Skip baris kosong
                if (empty(array_filter($row, fn($v) => $v !== '' && $v !== null))) {
                    return null;
                }

                try {
                    DB::beginTransaction();
                    $result = $rowHandler($row, $lineNo);
                    DB::commit();

                    if (isset($result['error'])) {
                        $stats['gagal']++;
                        $stats['errors'][] = $result['error'];
                    } elseif (isset($result['skipped'])) {
                        $stats['dilewati']++;
                    } elseif (isset($result['updated'])) {
                        $stats['berhasil']++;
                        $stats['diperbarui']++;
                    } else {
                        $stats['berhasil']++;
                        $stats['ditambah']++;
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $stats['gagal']++;
                    $stats['errors'][] = 'Baris ' . $lineNo . ': ' . $e->getMessage();
                    Log::error('[MasterDataImport] Error baris ' . $lineNo . ': ' . $e->getMessage());
                }

                return null;
            });
        } catch (\Throwable $e) {
            Log::error('[MasterDataImport] Gagal membaca file: ' . $e->getMessage());
            $stats['errors'][] = 'Gagal membaca file Excel: ' . $e->getMessage();
        }

        return $stats;
    }

    /**
     * Format response seragam untuk semua import.
     */
    private function importResponse(string $label, array $stats): \Illuminate\Http\JsonResponse
    {
        $total = $stats['berhasil'] + $stats['gagal'] + $stats['dilewati'];

        return response()->json([
            'success' => $stats['gagal'] === 0,
            'message' => "Import {$label} selesai. {$stats['berhasil']} berhasil, {$stats['dilewati']} dilewati, {$stats['gagal']} gagal dari {$total} baris data.",
            'summary' => [
                'total'      => $total,
                'berhasil'   => $stats['berhasil'],
                'ditambah'   => $stats['ditambah'],
                'diperbarui' => $stats['diperbarui'],
                'dilewati'   => $stats['dilewati'],
                'gagal'      => $stats['gagal'],
            ],
            'errors' => $stats['errors'],
        ], $stats['gagal'] > 0 && $stats['berhasil'] === 0 ? 422 : 200);
    }

    /**
     * Stream file Excel sebagai download response.
     *
     * @param  Collection $rows
     * @param  string     $filename
     */
    private function streamExcel(Collection $rows, string $filename)
    {
        return (new FastExcel($rows))->download($filename);
    }
}
