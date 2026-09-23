<?php

namespace App\Http\Controllers;

use App\Models\PointSetting;
use App\Models\PointTransaction;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * PointTransactionController
 *
 * Endpoint untuk:
 *   [Pasien]  GET  /api/points/balance              – saldo + ringkasan poin
 *   [Pasien]  GET  /api/points/history              – riwayat mutasi poin (paginate)
 *   [Pasien]  GET  /api/points/preview-booking      – preview diskon poin untuk booking
 *   [Admin]   GET  /api/admin/point-settings        – baca konfigurasi poin
 *   [Admin]   PUT  /api/admin/point-settings        – update konfigurasi poin (termasuk max_point_discount_percent)
 *   [Admin]   POST /api/admin/points/expire         – trigger expire manual
 *   [Admin]   GET  /api/admin/points/history        – riwayat semua pasien
 */
class PointTransactionController extends Controller
{
    public function __construct(protected PointService $pointService)
    {
    }

    // ================================================================
    // PASIEN ENDPOINTS
    // ================================================================

    /**
     * Saldo & ringkasan poin pasien yang sedang login.
     *
     * @group Poin Pasien
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "points_balance": 150,
     *     "soon_expiring_30d": 50,
     *     "point_rate": 10000,
     *     "point_expiry_days": 365,
     *     "is_active": true,
     *     "max_point_discount_percent": 50
     *   }
     * }
     */
    public function balance(Request $request): JsonResponse
    {
        $pasien = $request->user()?->pasien;

        if (!$pasien) {
            return response()->json(['success' => false, 'message' => 'Pasien tidak ditemukan.'], 404);
        }

        $setting = PointSetting::current();

        $soonExpiring = PointTransaction::where('id_pasien', $pasien->id_pasien)
            ->where('type', PointTransaction::TYPE_EARN)
            ->whereBetween('expired_at', [now(), now()->addDays(30)])
            ->whereDoesntHave('expiredRecords')
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'points_balance'             => (int) $pasien->points_balance,
                'soon_expiring_30d'          => (int) $soonExpiring,
                'point_rate'                 => $setting->point_rate,
                'point_expiry_days'          => $setting->point_expiry_days,
                'is_active'                  => $setting->is_active,
                'max_point_discount_percent' => $setting->max_point_discount_percent,
            ],
        ]);
    }

    /**
     * Riwayat mutasi poin pasien yang sedang login.
     *
     * @group Poin Pasien
     * @authenticated
     * @queryParam type string Filter tipe: EARN, REDEEM, EXPIRED. Opsional.
     * @queryParam per_page integer Jumlah per halaman. Default: 15.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => 'nullable|in:EARN,REDEEM,EXPIRED',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pasien = $request->user()?->pasien;

        if (!$pasien) {
            return response()->json(['success' => false, 'message' => 'Pasien tidak ditemukan.'], 404);
        }

        $query = PointTransaction::where('id_pasien', $pasien->id_pasien)
            ->with('booking:id_booking,booking_code,tanggal_kunjungan')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        [$items, $pagination] = $this->paginateQuery($query, $request, 15);

        return response()->json([
            'success'    => true,
            'pagination' => $pagination,
            'data'       => collect($items)->map(fn ($pt) => [
                'id'            => $pt->id,
                'type'          => $pt->type,
                'amount'        => $pt->amount,
                'balance_after' => $pt->balance_after,
                'expired_at'    => $pt->expired_at?->toDateTimeString(),
                'note'          => $pt->note,
                'booking_code'  => $pt->booking?->booking_code,
                'tanggal'       => $pt->created_at->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Preview kalkulasi diskon poin untuk booking yang akan dibuat.
     *
     * Pasien mengirim total tagihan + jumlah poin yang ingin dipakai,
     * sistem mengembalikan rincian diskon tanpa mengubah data apapun.
     *
     * @group Poin Pasien
     * @authenticated
     * @bodyParam total_tagihan integer required Total tagihan sebelum diskon poin. Example: 200000
     * @bodyParam points_to_use integer Jumlah poin yang ingin dipakai. 0 = tidak pakai. Example: 50000
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "is_active": true,
     *     "points_balance": 150000,
     *     "points_to_use": 50000,
     *     "discount_rp": 50000,
     *     "total_after_discount": 150000,
     *     "max_points_redeemable": 100000,
     *     "max_discount_rp": 100000,
     *     "max_discount_percent": 50,
     *     "error": null
     *   }
     * }
     */
    public function previewBooking(Request $request): JsonResponse
    {
        $request->validate([
            'total_tagihan' => 'required|integer|min:1',
            'points_to_use' => 'nullable|integer|min:0',
        ]);

        $pasien = $request->user()?->pasien;

        if (!$pasien) {
            return response()->json(['success' => false, 'message' => 'Pasien tidak ditemukan.'], 404);
        }

        $preview = $this->pointService->previewRedeem(
            totalTagihan:  (int) $request->input('total_tagihan'),
            pointsBalance: (int) $pasien->points_balance,
            pointsToUse:   $request->filled('points_to_use') ? (int) $request->input('points_to_use') : 0,
        );

        $statusCode = $preview['error'] ? 422 : 200;

        return response()->json([
            'success' => $preview['error'] === null,
            'data'    => $preview,
        ], $statusCode);
    }


    // ================================================================
    // ADMIN ENDPOINTS
    // ================================================================

    /**
     * Baca konfigurasi poin saat ini.
     *
     * @group Admin – Konfigurasi Poin
     * @authenticated
     */
    public function getSettings(): JsonResponse
    {
        $setting = PointSetting::current();

        return response()->json([
            'success' => true,
            'data'    => [
                'point_rate'                 => $setting->point_rate,
                'point_expiry_days'          => $setting->point_expiry_days,
                'is_active'                  => $setting->is_active,
                'max_point_discount_percent' => $setting->max_point_discount_percent,
                'updated_by'                 => $setting->updated_by,
                'updated_at'                 => $setting->updated_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Update konfigurasi poin.
     *
     * @group Admin – Konfigurasi Poin
     * @authenticated
     * @bodyParam point_rate integer required Nominal Rp untuk mendapatkan 1 poin. Min: 1000. Example: 10000
     * @bodyParam point_expiry_days integer required Masa berlaku poin (hari). Min: 1. Example: 365
     * @bodyParam is_active boolean Aktifkan fitur poin. Example: true
     * @bodyParam max_point_discount_percent integer Persentase maks dari total tagihan yang bisa dipotong poin (1–100). Default: 50. Example: 50
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'point_rate'                 => 'required|integer|min:1000',
            'point_expiry_days'          => 'required|integer|min:1|max:3650',
            'is_active'                  => 'nullable|boolean',
            'max_point_discount_percent' => 'nullable|integer|min:1|max:100',
        ]);

        $setting = PointSetting::firstOrCreate(['id' => 1]);
        $setting->fill([
            'point_rate'                 => $validated['point_rate'],
            'point_expiry_days'          => $validated['point_expiry_days'],
            'is_active'                  => $validated['is_active'] ?? $setting->is_active,
            'max_point_discount_percent' => $validated['max_point_discount_percent'] ?? $setting->max_point_discount_percent,
            'updated_by'                 => (string) ($request->user()?->id_admin ?? $request->user()?->getKey()),
        ]);
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi poin berhasil diperbarui.',
            'data'    => [
                'point_rate'                 => $setting->point_rate,
                'point_expiry_days'          => $setting->point_expiry_days,
                'is_active'                  => $setting->is_active,
                'max_point_discount_percent' => $setting->max_point_discount_percent,
            ],
        ]);
    }

    /**
     * Trigger expire poin secara manual (admin only).
     *
     * @group Admin – Konfigurasi Poin
     * @authenticated
     */
    public function triggerExpire(): JsonResponse
    {
        $result = $this->pointService->expireAll();

        return response()->json([
            'success' => true,
            'message' => 'Proses expire poin selesai.',
            'data'    => [
                'expired_rows'      => $result['expired_rows'],
                'affected_patients' => $result['affected_patients'],
            ],
        ]);
    }

    /**
     * Riwayat mutasi poin semua pasien (admin view).
     *
     * @group Admin – Konfigurasi Poin
     * @authenticated
     * @queryParam id_pasien integer Filter per pasien. Opsional.
     * @queryParam type string Filter tipe: EARN, REDEEM, EXPIRED. Opsional.
     * @queryParam per_page integer Default: 20.
     */
    public function adminHistory(Request $request): JsonResponse
    {
        $request->validate([
            'id_pasien' => 'nullable|integer|exists:pasiens,id_pasien',
            'type'      => 'nullable|in:EARN,REDEEM,EXPIRED',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        $query = PointTransaction::with([
                'pasien:id_pasien,nama_lengkap,no_hp',
                'booking:id_booking,booking_code',
            ])
            ->latest();

        if ($request->filled('id_pasien')) {
            $query->where('id_pasien', $request->id_pasien);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        [$items, $pagination] = $this->paginateQuery($query, $request, 20);

        return response()->json([
            'success'    => true,
            'pagination' => $pagination,
            'data'       => collect($items)->map(fn ($pt) => [
                'id'            => $pt->id,
                'id_pasien'     => $pt->id_pasien,
                'nama_pasien'   => $pt->pasien?->nama_lengkap,
                'type'          => $pt->type,
                'amount'        => $pt->amount,
                'balance_after' => $pt->balance_after,
                'expired_at'    => $pt->expired_at?->toDateTimeString(),
                'note'          => $pt->note,
                'booking_code'  => $pt->booking?->booking_code,
                'tanggal'       => $pt->created_at->toDateTimeString(),
            ]),
        ]);
    }
}
