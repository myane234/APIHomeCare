<?php

namespace App\Services;

use App\Models\Withdrawal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IrisService
 *
 * Service layer untuk komunikasi dengan Midtrans Iris API.
 * Iris adalah layanan disbursement (payout) dari Midtrans untuk
 * mengirim uang ke rekening bank nakes.
 *
 * Dokumentasi: https://iris-docs.midtrans.com/
 */
class IrisService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $merchantKey;

    public function __construct()
    {
        $this->apiKey      = config('services.iris.api_key', '');
        $this->merchantKey = config('services.iris.merchant_key', '');
        $this->baseUrl     = config('services.iris.base_url', 'https://app.sandbox.iris.midtrans.com/api/v1');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Public Methods
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Kirim payout ke rekening nakes via Iris.
     *
     * @param  Withdrawal $withdrawal  Model withdrawal yang sudah disimpan dengan status pending
     * @return array{success: bool, reference_no: ?string, response: array, error: ?string}
     */
    public function sendPayout(Withdrawal $withdrawal): array
    {
        try {
            $payload = [
                'payouts' => [
                    [
                        'beneficiary_name'    => $withdrawal->beneficiary_name,
                        'beneficiary_account' => $withdrawal->beneficiary_account,
                        'beneficiary_bank'    => $withdrawal->beneficiary_bank, // lowercase
                        'beneficiary_email'   => $withdrawal->tenagaMedis?->user?->email ?? '',
                        'amount'              => (string) (int) $withdrawal->jumlah,
                        'notes'               => 'Withdraw saldo Nakes #' . $withdrawal->id_withdrawal,
                    ]
                ]
            ];

            $response = $this->request('POST', '/payouts', $payload);

            if ($response['status_code'] === 201 || $response['status_code'] === 200) {
                // Iris mengembalikan array payouts, ambil yang pertama
                $payoutData  = $response['body']['payouts'][0] ?? [];
                $referenceNo = $payoutData['reference_no'] ?? null;

                Log::info("[IrisService] Payout berhasil dibuat. Withdrawal #{$withdrawal->id_withdrawal}, reference_no: {$referenceNo}");

                return [
                    'success'      => true,
                    'reference_no' => $referenceNo,
                    'response'     => $response['body'],
                    'error'        => null,
                ];
            }

            $errorMsg = $response['body']['errors'][0] ?? ($response['body']['message'] ?? 'Unknown error from Iris');
            Log::error("[IrisService] Payout gagal. Withdrawal #{$withdrawal->id_withdrawal}. Status: {$response['status_code']}. Error: {$errorMsg}");

            return [
                'success'      => false,
                'reference_no' => null,
                'response'     => $response['body'],
                'error'        => $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::error("[IrisService] Exception saat sendPayout #{$withdrawal->id_withdrawal}: " . $e->getMessage());

            return [
                'success'      => false,
                'reference_no' => null,
                'response'     => [],
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * Cek status payout berdasarkan reference_no dari Iris.
     *
     * @param  string $referenceNo
     * @return array{status: ?string, response: array, error: ?string}
     */
    public function getPayoutStatus(string $referenceNo): array
    {
        try {
            $response = $this->request('GET', "/payouts/{$referenceNo}");

            if ($response['status_code'] === 200) {
                $status = $response['body']['status'] ?? null;

                return [
                    'status'   => $status,
                    'response' => $response['body'],
                    'error'    => null,
                ];
            }

            return [
                'status'   => null,
                'response' => $response['body'],
                'error'    => $response['body']['message'] ?? 'Unknown error',
            ];
        } catch (\Throwable $e) {
            Log::error("[IrisService] Exception getPayoutStatus {$referenceNo}: " . $e->getMessage());

            return [
                'status'   => null,
                'response' => [],
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Cek saldo merchant di Iris (bukan saldo nakes, tapi saldo pool merchant).
     *
     * @return array{balance: ?float, response: array, error: ?string}
     */
    public function getMerchantBalance(): array
    {
        try {
            $response = $this->request('GET', '/balance');

            if ($response['status_code'] === 200) {
                $balance = isset($response['body']['balance'])
                    ? (float) $response['body']['balance']
                    : null;

                return [
                    'balance'  => $balance,
                    'response' => $response['body'],
                    'error'    => null,
                ];
            }

            return [
                'balance'  => null,
                'response' => $response['body'],
                'error'    => $response['body']['message'] ?? 'Unknown error',
            ];
        } catch (\Throwable $e) {
            Log::error("[IrisService] Exception getMerchantBalance: " . $e->getMessage());

            return [
                'balance'  => null,
                'response' => [],
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Validasi webhook callback dari Iris menggunakan merchant_key.
     *
     * Iris mengirimkan header X-Callback-Signature yang berisi
     * HMAC-SHA512 dari raw request body menggunakan merchant_key.
     *
     * @param  string $rawBody       Raw request body dari webhook
     * @param  string $signature     Nilai header X-Callback-Signature
     * @return bool
     */
    public function validateWebhookSignature(string $rawBody, string $signature): bool
    {
        if (empty($this->merchantKey)) {
            Log::warning('[IrisService] IRIS_MERCHANT_KEY kosong, skip validasi signature webhook.');
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $this->merchantKey);

        return hash_equals($expected, $signature);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Buat HTTP request ke Iris API dengan Basic Auth.
     *
     * Iris menggunakan Basic Auth: username = api_key, password = '' (kosong).
     *
     * @param  string $method  GET|POST
     * @param  string $path    Endpoint path, contoh: '/payouts'
     * @param  array  $body    Request body untuk POST
     * @return array{status_code: int, body: array}
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        $http = Http::withBasicAuth($this->apiKey, '')
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET'  => $http->get($url),
            'POST' => $http->post($url, $body),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        return [
            'status_code' => $response->status(),
            'body'        => $response->json() ?? [],
        ];
    }
}
