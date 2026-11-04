<?php

namespace App\Http\Controllers;

use App\Models\TenagaMedis;
use App\Models\Withdrawal;
use App\Services\IrisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WithdrawalController
 *
 * Mengelola fitur withdraw saldo nakes via Midtrans Iris.
 * Semua aksi dilakukan otomatis tanpa approval admin.
 *
 * @group Nakes - Withdraw
 */
class WithdrawalController extends Controller
{
    public function __construct(protected IrisService $irisService)
    {
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper: ambil TenagaMedis dari user yang login
    // ──────────────────────────────────────────────────────────────────────

    private function getNakes(Request $request): ?TenagaMedis
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user instanceof TenagaMedis) {
            return $user;
        }

        return TenagaMedis::where('id_user', $user->id_user ?? $user->id)->first();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper: format Rupiah
    // ──────────────────────────────────────────────────────────────────────

    private function rupiah(float $nominal): string
    {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /nakes/saldo
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Saldo Terkini Nakes
     *
     * Menampilkan saldo tersedia nakes yang siap di-withdraw,
     * beserta informasi rekening bank yang terdaftar.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "saldo": 250000,
     *     "saldo_format": "Rp 250.000",
     *     "minimum_withdraw": 50000,
     *     "minimum_withdraw_format": "Rp 50.000",
     *     "bisa_withdraw": true,
     *     "rekening": {
     *       "nama_bank": "Bank Central Asia (BCA)",
     *       "kode_bank": "BCA",
     *       "nama_pemilik": "John Doe",
     *       "no_rekening": "1234567890"
     *     }
     *   }
     * }
     */
    public function saldo(Request $request)
    {
        $nakes = $this->getNakes($request);
        if (!$nakes) {
            return response()->json(['success' => false, 'message' => 'Data tenaga medis tidak ditemukan.'], 404);
        }

        $saldo = (float) $nakes->saldo;
        $nakes->loadMissing('bank');

        $rekening = null;
        if ($nakes->no_rekening) {
            $rekening = [
                'nama_bank'    => $nakes->bank?->nama_bank ?? '-',
                'kode_bank'    => $nakes->bank?->kode_bank ?? '-',
                'nama_pemilik' => $nakes->nama_pemilik_rekening,
                'no_rekening'  => $nakes->no_rekening,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'saldo'                   => $saldo,
                'saldo_format'            => $this->rupiah($saldo),
                'minimum_withdraw'        => Withdrawal::MINIMUM_WITHDRAW,
                'minimum_withdraw_format' => $this->rupiah(Withdrawal::MINIMUM_WITHDRAW),
                'bisa_withdraw'           => $saldo >= Withdrawal::MINIMUM_WITHDRAW,
                'rekening'                => $rekening,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // POST /nakes/withdraw
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Request Withdraw
     *
     * Nakes mengajukan penarikan saldo. Saldo langsung di-hold dan
     * dikirim otomatis ke rekening nakes via Midtrans Iris.
     * Minimal withdraw Rp 50.000.
     *
     * @authenticated
     *
     * @bodyParam jumlah integer required Nominal withdraw (minimal 50000). Example: 150000
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Withdraw berhasil diajukan dan sedang diproses.",
     *   "data": {
     *     "id_withdrawal": 1,
     *     "jumlah": 150000,
     *     "jumlah_format": "Rp 150.000",
     *     "status": "processing",
     *     "status_label": "Diproses",
     *     "rekening_tujuan": {
     *       "nama_bank": "Bank Central Asia (BCA)",
     *       "nama_pemilik": "John Doe",
     *       "no_rekening": "1234567890"
     *     },
     *     "created_at": "2026-11-04T10:00:00+07:00"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Saldo tidak mencukupi."
     * }
     */
    public function requestWithdraw(Request $request)
    {
        $request->validate([
            'jumlah' => [
                'required',
                'integer',
                'min:' . Withdrawal::MINIMUM_WITHDRAW,
            ],
        ], [
            'jumlah.min' => 'Minimal withdraw adalah ' . $this->rupiah(Withdrawal::MINIMUM_WITHDRAW) . '.',
        ]);

        $nakes = $this->getNakes($request);
        if (!$nakes) {
            return response()->json(['success' => false, 'message' => 'Data tenaga medis tidak ditemukan.'], 404);
        }

        // Validasi status nakes harus approved
        if ($nakes->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya nakes yang sudah disetujui yang dapat melakukan withdraw.',
            ], 403);
        }

        // Validasi kelengkapan data rekening
        if (!$nakes->hasCompleteRekening()) {
            return response()->json([
                'success' => false,
                'message' => 'Data rekening bank belum lengkap. Lengkapi profil terlebih dahulu.',
            ], 422);
        }

        $jumlah = (int) $request->jumlah;

        // Load bank untuk dapat kode_bank
        $nakes->loadMissing('bank');

        if (!$nakes->bank) {
            return response()->json([
                'success' => false,
                'message' => 'Data bank tidak ditemukan.',
            ], 422);
        }

        return DB::transaction(function () use ($nakes, $jumlah) {
            // Lock row untuk hindari race condition (double withdraw)
            $nakesFresh = TenagaMedis::where('id_tenaga_medis', $nakes->id_tenaga_medis)
                                     ->lockForUpdate()
                                     ->first();

            if ((float) $nakesFresh->saldo < $jumlah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Saldo tidak mencukupi. Saldo tersedia: ' . $this->rupiah((float) $nakesFresh->saldo) . '.',
                ], 422);
            }

            // Hold saldo (kurangi dulu sebelum kirim ke Iris)
            $nakesFresh->decrement('saldo', $jumlah);

            // Buat record withdrawal dengan snapshot rekening saat ini
            $withdrawal = Withdrawal::create([
                'id_tenaga_medis'      => $nakes->id_tenaga_medis,
                'jumlah'               => $jumlah,
                'status'               => Withdrawal::STATUS_PENDING,
                'beneficiary_name'     => $nakes->nama_pemilik_rekening,
                'beneficiary_account'  => $nakes->no_rekening,
                'beneficiary_bank'     => strtolower($nakes->bank->kode_bank), // Iris pakai lowercase
                'beneficiary_bank_name'=> $nakes->bank->nama_bank,
            ]);

            // Kirim ke Midtrans Iris
            $result = $this->irisService->sendPayout($withdrawal);

            if ($result['success']) {
                $withdrawal->update([
                    'status'          => Withdrawal::STATUS_PROCESSING,
                    'iris_reference_no' => $result['reference_no'],
                    'iris_response'   => $result['response'],
                ]);
            } else {
                // Iris gagal — rollback saldo
                $nakesFresh->increment('saldo', $jumlah);
                $withdrawal->update([
                    'status'         => Withdrawal::STATUS_FAILED,
                    'iris_response'  => $result['response'],
                    'failure_reason' => $result['error'] ?? 'Gagal mengirim ke Midtrans Iris.',
                ]);

                Log::error("[WithdrawalController] Iris gagal untuk withdrawal #{$withdrawal->id_withdrawal}: {$result['error']}");

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memproses withdraw. Silakan coba lagi beberapa saat.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'Withdraw berhasil diajukan dan sedang diproses.',
                'data'    => $this->formatWithdrawal($withdrawal),
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /nakes/withdraw/riwayat
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Riwayat Withdraw Nakes
     *
     * Menampilkan daftar semua riwayat withdraw nakes yang sedang login,
     * diurutkan dari yang terbaru.
     *
     * @authenticated
     *
     * @queryParam page integer Halaman. Default: 1. Example: 1
     * @queryParam per_page integer Jumlah per halaman (max 50). Default: 15. Example: 15
     * @queryParam status string Filter status (pending|processing|success|failed). Example: success
     *
     * @response 200 {
     *   "success": true,
     *   "pagination": {
     *     "total": 10,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "total_pages": 1
     *   },
     *   "data": []
     * }
     */
    public function riwayat(Request $request)
    {
        $request->validate([
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'status'   => 'nullable|in:pending,processing,success,failed',
        ]);

        $nakes = $this->getNakes($request);
        if (!$nakes) {
            return response()->json(['success' => false, 'message' => 'Data tenaga medis tidak ditemukan.'], 404);
        }

        $perPage = $request->input('per_page', 15);

        $query = Withdrawal::where('id_tenaga_medis', $nakes->id_tenaga_medis)
                           ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->paginate($perPage);

        return response()->json([
            'success'    => true,
            'pagination' => [
                'total'          => $withdrawals->total(),
                'count'          => $withdrawals->count(),
                'per_page'       => $withdrawals->perPage(),
                'current_page'   => $withdrawals->currentPage(),
                'total_pages'    => $withdrawals->lastPage(),
                'has_more_pages' => $withdrawals->hasMorePages(),
            ],
            'data' => $withdrawals->map(fn($w) => $this->formatWithdrawal($w)),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /nakes/withdraw/status?reference_no=xxx
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Detail Status Withdraw
     *
     * Menampilkan detail satu transaksi withdraw berdasarkan reference_no
     * dari Midtrans Iris. Endpoint ini tidak mengekspos id_withdrawal secara
     * publik — hanya nakes yang bersangkutan yang bisa mengakses.
     *
     * @authenticated
     *
     * @queryParam reference_no string required Reference number dari Iris (ada di riwayat). Example: ytfbs8af9ghz40xhab
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id_withdrawal": 1,
     *     "jumlah": 150000,
     *     "jumlah_format": "Rp 150.000",
     *     "status": "success",
     *     "status_label": "Berhasil",
     *     "iris_reference_no": "ytfbs8af9ghz40xhab",
     *     "rekening_tujuan": {},
     *     "created_at": "2026-11-04T10:00:00+07:00",
     *     "processed_at": "2026-11-04T10:05:00+07:00"
     *   }
     * }
     */
    public function detail(Request $request)
    {
        $request->validate([
            'reference_no' => 'required|string',
        ]);

        $nakes = $this->getNakes($request);
        if (!$nakes) {
            return response()->json(['success' => false, 'message' => 'Data tenaga medis tidak ditemukan.'], 404);
        }

        $withdrawal = Withdrawal::where('id_tenaga_medis', $nakes->id_tenaga_medis)
                                ->where('iris_reference_no', $request->reference_no)
                                ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Data withdraw tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatWithdrawal($withdrawal, true),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper: format response withdrawal
    // ──────────────────────────────────────────────────────────────────────

    private function formatWithdrawal(Withdrawal $w, bool $detail = false): array
    {
        $data = [
            'id_withdrawal'    => $w->id_withdrawal,
            'jumlah'           => (float) $w->jumlah,
            'jumlah_format'    => $w->jumlahFormat(),
            'status'           => $w->status,
            'status_label'     => $w->statusLabel(),
            'iris_reference_no'=> $w->iris_reference_no,
            'rekening_tujuan'  => [
                'nama_bank'    => $w->beneficiary_bank_name,
                'kode_bank'    => strtoupper($w->beneficiary_bank),
                'nama_pemilik' => $w->beneficiary_name,
                'no_rekening'  => $w->beneficiary_account,
            ],
            'created_at'    => $w->created_at?->toIso8601String(),
            'processed_at'  => $w->processed_at?->toIso8601String(),
        ];

        if ($detail) {
            $data['failure_reason'] = $w->failure_reason;
        }

        return $data;
    }
}
