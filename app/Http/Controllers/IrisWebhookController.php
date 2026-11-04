<?php

namespace App\Http\Controllers;

use App\Models\TenagaMedis;
use App\Models\Withdrawal;
use App\Services\IrisService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IrisWebhookController
 *
 * Menerima callback (webhook) dari Midtrans Iris setelah transfer
 * ke rekening nakes diproses. Endpoint ini tidak membutuhkan auth
 * karena dipanggil langsung oleh server Iris.
 *
 * Iris mengirimkan header X-Callback-Signature untuk validasi.
 *
 * @group Webhook
 */
class IrisWebhookController extends Controller
{
    public function __construct(
        protected IrisService $irisService,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Iris Webhook Callback
     *
     * Endpoint ini dipanggil oleh server Midtrans Iris ketika status
     * payout berubah (berhasil atau gagal). Tidak memerlukan autentikasi
     * karena divalidasi menggunakan HMAC-SHA512 signature.
     *
     * @unauthenticated
     *
     * @response 200 {"status": "ok"}
     * @response 400 {"status": "invalid_signature"}
     * @response 404 {"status": "withdrawal_not_found"}
     */
    public function handle(Request $request)
    {
        $rawBody  = $request->getContent();
        $signature = $request->header('X-Callback-Signature', '');

        // Validasi signature dari Iris
        if (!$this->irisService->validateWebhookSignature($rawBody, $signature)) {
            Log::warning('[IrisWebhook] Invalid signature. IP: ' . $request->ip());
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payload = $request->json()->all();

        Log::info('[IrisWebhook] Payload diterima', ['payload' => $payload]);

        // Iris mengirim array 'payouts' dalam payload
        $payouts = $payload['payouts'] ?? [];

        if (empty($payouts)) {
            return response()->json(['status' => 'ok']);
        }

        foreach ($payouts as $payout) {
            $this->processPayout($payout);
        }

        return response()->json(['status' => 'ok']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private: proses satu item payout dari webhook
    // ──────────────────────────────────────────────────────────────────────

    private function processPayout(array $payout): void
    {
        $referenceNo = $payout['reference_no'] ?? null;
        $status      = $payout['status'] ?? null;

        if (!$referenceNo || !$status) {
            Log::warning('[IrisWebhook] Payload payout tidak lengkap', ['payout' => $payout]);
            return;
        }

        $withdrawal = Withdrawal::where('iris_reference_no', $referenceNo)->first();

        if (!$withdrawal) {
            Log::warning("[IrisWebhook] Withdrawal dengan reference_no {$referenceNo} tidak ditemukan.");
            return;
        }

        // Idempotensi: skip jika sudah final
        if (in_array($withdrawal->status, [Withdrawal::STATUS_SUCCESS, Withdrawal::STATUS_FAILED])) {
            Log::info("[IrisWebhook] Withdrawal #{$withdrawal->id_withdrawal} sudah final ({$withdrawal->status}), skip.");
            return;
        }

        DB::transaction(function () use ($withdrawal, $payout, $status) {
            // Status dari Iris: 'processed' = berhasil, 'failed' = gagal
            $irisSuccess = in_array(strtolower($status), ['processed', 'completed', 'success']);
            $irisFailed  = in_array(strtolower($status), ['failed', 'rejected', 'cancelled']);

            if ($irisSuccess) {
                $withdrawal->update([
                    'status'        => Withdrawal::STATUS_SUCCESS,
                    'iris_response' => $payout,
                    'processed_at'  => now(),
                ]);

                Log::info("[IrisWebhook] Withdrawal #{$withdrawal->id_withdrawal} berhasil. Reference: {$withdrawal->iris_reference_no}");

                $this->sendNotifikasi($withdrawal, true);

            } elseif ($irisFailed) {
                // Kembalikan saldo ke nakes karena transfer gagal
                TenagaMedis::where('id_tenaga_medis', $withdrawal->id_tenaga_medis)
                           ->lockForUpdate()
                           ->first()
                           ?->increment('saldo', (float) $withdrawal->jumlah);

                $failureReason = $payout['failure_reason'] ?? $payout['reason'] ?? 'Transfer gagal di sisi Midtrans Iris.';

                $withdrawal->update([
                    'status'         => Withdrawal::STATUS_FAILED,
                    'iris_response'  => $payout,
                    'failure_reason' => $failureReason,
                    'processed_at'   => now(),
                ]);

                Log::error("[IrisWebhook] Withdrawal #{$withdrawal->id_withdrawal} GAGAL. Saldo dikembalikan. Reason: {$failureReason}");

                $this->sendNotifikasi($withdrawal, false);

            } else {
                // Status lain (misal 'queued', 'pending') — update response tapi jangan ubah status
                $withdrawal->update([
                    'iris_response' => $payout,
                ]);

                Log::info("[IrisWebhook] Withdrawal #{$withdrawal->id_withdrawal} status Iris: {$status} (belum final).");
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private: kirim notifikasi in-app ke nakes
    // ──────────────────────────────────────────────────────────────────────

    private function sendNotifikasi(Withdrawal $withdrawal, bool $berhasil): void
    {
        try {
            $nakes = $withdrawal->tenagaMedis ?? TenagaMedis::find($withdrawal->id_tenaga_medis);
            if (!$nakes || !$nakes->id_user) {
                return;
            }

            $jumlahFormat = $withdrawal->jumlahFormat();

            if ($berhasil) {
                $this->notificationService->sendByCode(
                    templateCode: 'withdraw_success',
                    userId: $nakes->id_user,
                    userRole: 'nakes',
                    variables: [
                        'nakes_name'  => $nakes->nama_lengkap ?? 'Nakes',
                        'jumlah'      => $jumlahFormat,
                        'bank'        => $withdrawal->beneficiary_bank_name,
                        'no_rekening' => $withdrawal->beneficiary_account,
                    ],
                    options: [
                        'action_url' => '/nakes/withdraw/riwayat',
                        'data'       => [
                            'id_withdrawal'    => $withdrawal->id_withdrawal,
                            'iris_reference_no'=> $withdrawal->iris_reference_no,
                            'type'             => 'withdraw_success',
                        ],
                        'created_by' => 'system',
                    ]
                );
            } else {
                $this->notificationService->sendByCode(
                    templateCode: 'withdraw_failed',
                    userId: $nakes->id_user,
                    userRole: 'nakes',
                    variables: [
                        'nakes_name'     => $nakes->nama_lengkap ?? 'Nakes',
                        'jumlah'         => $jumlahFormat,
                        'failure_reason' => $withdrawal->failure_reason ?? 'Transfer tidak berhasil.',
                    ],
                    options: [
                        'action_url' => '/nakes/withdraw/riwayat',
                        'data'       => [
                            'id_withdrawal'    => $withdrawal->id_withdrawal,
                            'iris_reference_no'=> $withdrawal->iris_reference_no,
                            'type'             => 'withdraw_failed',
                        ],
                        'created_by' => 'system',
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Jangan sampai error notifikasi mengganggu proses utama
            Log::error("[IrisWebhook] Gagal kirim notifikasi withdrawal #{$withdrawal->id_withdrawal}: " . $e->getMessage());
        }
    }
}
