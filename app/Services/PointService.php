<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Pasien;
use App\Models\PointSetting;
use App\Models\PointTransaction;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PointService
 *
 * Mengelola seluruh logika mutasi poin pasien:
 *   - earn()         : Tambah poin saat transaksi/booking selesai
 *   - redeem()       : Potong poin saat pasien memakai poin untuk diskon
 *   - previewRedeem(): Kalkulasi preview diskon poin sebelum booking dibuat
 *   - expireAll()    : Hanguskan semua EARN yang sudah melewati expired_at
 */
class PointService
{
    /**
     * Tambah poin (EARN) untuk pasien berdasarkan jumlah transaksi.
     *
     * Dipanggil dari Observer saat booking/transaksi berstatus Selesai atau Paid.
     *
     * @param  Pasien       $pasien
     * @param  int|float    $jumlahTransaksi  Nominal transaksi dalam Rupiah
     * @param  Booking|null $booking
     * @param  Transaksi|null $transaksi
     * @return PointTransaction|null   null jika poin yang didapat = 0
     */
    public function earn(
        Pasien $pasien,
        int|float $jumlahTransaksi,
        ?Booking $booking = null,
        ?Transaksi $transaksi = null
    ): ?PointTransaction {
        $setting = PointSetting::current();

        if (!$setting->is_active) {
            return null;
        }

        $earnedPoints = PointSetting::calculateEarn($jumlahTransaksi);

        if ($earnedPoints <= 0) {
            return null;
        }

        // Cek duplikasi — satu booking hanya boleh menghasilkan satu EARN
        if ($booking && PointTransaction::where('id_booking', $booking->id_booking)
                                        ->where('type', PointTransaction::TYPE_EARN)
                                        ->exists()) {
            Log::info("[PointService] EARN sudah ada untuk booking #{$booking->id_booking}, skip.");
            return null;
        }

        return DB::transaction(function () use ($pasien, $earnedPoints, $booking, $transaksi, $setting) {
            // Lock baris pasien agar tidak race condition
            $pasien = Pasien::where('id_pasien', $pasien->id_pasien)->lockForUpdate()->first();

            $newBalance = $pasien->points_balance + $earnedPoints;

            $expiredAt = now()->addDays($setting->point_expiry_days);

            $note = $booking
                ? "Poin dari booking #{$booking->booking_code}"
                : "Poin dari transaksi";

            $pt = PointTransaction::create([
                'id_pasien'    => $pasien->id_pasien,
                'id_booking'   => $booking?->id_booking,
                'id_transaksi' => $transaksi?->id_transaksi,
                'type'         => PointTransaction::TYPE_EARN,
                'amount'       => $earnedPoints,
                'balance_after'=> $newBalance,
                'expired_at'   => $expiredAt,
                'note'         => $note,
            ]);

            $pasien->update(['points_balance' => $newBalance]);

            Log::info("[PointService] EARN {$earnedPoints} poin untuk pasien #{$pasien->id_pasien}. Balance: {$newBalance}.");

            return $pt;
        });
    }

    /**
     * Potong poin (REDEEM) saat pasien memakai poin untuk diskon booking.
     *
     * @param  Pasien        $pasien
     * @param  int           $pointsToRedeem  Jumlah poin yang akan dipakai
     * @param  Booking|null  $booking         Booking terkait (opsional)
     * @param  string        $note            Keterangan
     * @return PointTransaction
     *
     * @throws \InvalidArgumentException  Jika pointsToRedeem <= 0
     * @throws \RuntimeException          Jika saldo tidak cukup
     */
    public function redeem(
        Pasien $pasien,
        int $pointsToRedeem,
        ?Booking $booking = null,
        string $note = 'Redeem poin'
    ): PointTransaction {
        if ($pointsToRedeem <= 0) {
            throw new \InvalidArgumentException('Jumlah poin redeem harus lebih dari 0.');
        }

        return DB::transaction(function () use ($pasien, $pointsToRedeem, $booking, $note) {
            $pasien = Pasien::where('id_pasien', $pasien->id_pasien)->lockForUpdate()->first();

            if ($pasien->points_balance < $pointsToRedeem) {
                throw new \RuntimeException(
                    "Saldo poin tidak cukup. Tersedia: {$pasien->points_balance}, diminta: {$pointsToRedeem}."
                );
            }

            $newBalance = $pasien->points_balance - $pointsToRedeem;

            $pt = PointTransaction::create([
                'id_pasien'    => $pasien->id_pasien,
                'id_booking'   => $booking?->id_booking,
                'type'         => PointTransaction::TYPE_REDEEM,
                'amount'       => $pointsToRedeem,
                'balance_after'=> $newBalance,
                'note'         => $note,
            ]);

            $pasien->update(['points_balance' => $newBalance]);

            Log::info("[PointService] REDEEM {$pointsToRedeem} poin untuk pasien #{$pasien->id_pasien}. Balance: {$newBalance}.");

            return $pt;
        });
    }

    /**
     * Preview kalkulasi diskon poin sebelum booking dibuat.
     *
     * Tidak melakukan mutasi apapun ke database.
     * Digunakan oleh endpoint preview dan juga oleh BookingController.store().
     *
     * Skema:
     *   - 1 poin = Rp 1
     *   - Pasien bebas memilih pakai berapa poin (0 = tidak pakai)
     *   - Maks poin yang bisa dipakai: min(saldo, floor(total * max_percent / 100))
     *   - Tagihan tidak boleh jadi 0 (minimal Rp 1 tetap dibayar)
     *
     * @param  int|float $totalTagihan   Total tagihan sebelum diskon poin
     * @param  int       $pointsBalance  Saldo poin pasien
     * @param  int|null  $pointsToUse    Poin yang ingin dipakai pasien (null = auto-hitung maks)
     * @return array{
     *     is_active: bool,
     *     points_balance: int,
     *     points_to_use: int,
     *     discount_rp: int,
     *     total_after_discount: int,
     *     max_points_redeemable: int,
     *     max_discount_rp: int,
     *     max_discount_percent: int,
     *     error: string|null
     * }
     */
    public function previewRedeem(
        int|float $totalTagihan,
        int $pointsBalance,
        ?int $pointsToUse = null
    ): array {
        $setting = PointSetting::current();

        $base = [
            'is_active'            => $setting->is_active,
            'points_balance'       => $pointsBalance,
            'max_discount_percent' => $setting->max_point_discount_percent,
        ];

        // Hitung batas maksimal terlebih dahulu
        $maxInfo = PointSetting::calculateMaxRedeemablePoints($totalTagihan, $pointsBalance);

        $base['max_points_redeemable'] = $maxInfo['max_points_redeemable'];
        $base['max_discount_rp']       = $maxInfo['max_discount_rp'];

        if (!$setting->is_active) {
            return array_merge($base, [
                'points_to_use'        => 0,
                'discount_rp'          => 0,
                'total_after_discount' => (int) $totalTagihan,
                'error'                => 'Fitur poin sedang tidak aktif.',
            ]);
        }

        // Jika pasien tidak kirim pointsToUse, default ke 0 (tidak pakai poin)
        $pointsToUse = $pointsToUse ?? 0;

        if ($pointsToUse < 0) {
            return array_merge($base, [
                'points_to_use'        => 0,
                'discount_rp'          => 0,
                'total_after_discount' => (int) $totalTagihan,
                'error'                => 'Jumlah poin tidak boleh negatif.',
            ]);
        }

        // Validasi tidak melebihi saldo
        if ($pointsToUse > $pointsBalance) {
            return array_merge($base, [
                'points_to_use'        => 0,
                'discount_rp'          => 0,
                'total_after_discount' => (int) $totalTagihan,
                'error'                => "Saldo poin tidak cukup. Tersedia: {$pointsBalance}, diminta: {$pointsToUse}.",
            ]);
        }

        // Validasi tidak melebihi batas maks
        if ($pointsToUse > $maxInfo['max_points_redeemable']) {
            return array_merge($base, [
                'points_to_use'        => 0,
                'discount_rp'          => 0,
                'total_after_discount' => (int) $totalTagihan,
                'error'                => "Maksimal penggunaan poin adalah {$maxInfo['max_points_redeemable']} poin "
                                        . "({$setting->max_point_discount_percent}% dari total tagihan).",
            ]);
        }

        $discountRp          = $pointsToUse; // 1 poin = Rp 1
        $totalAfterDiscount  = (int) $totalTagihan - $discountRp;

        return array_merge($base, [
            'points_to_use'        => $pointsToUse,
            'discount_rp'          => $discountRp,
            'total_after_discount' => $totalAfterDiscount,
            'error'                => null,
        ]);
    }

    /**
     * Hanguskan semua poin EARN yang sudah melewati expired_at.
     *
     * Dipanggil setiap hari oleh Artisan command `points:expire`.
     *
     * Algoritma per-pasien:
     *   1. Temukan semua baris EARN kedaluwarsa yang belum di-expire.
     *   2. Hitung total poin yang akan hangus.
     *   3. Kurangi points_balance pasien.
     *   4. Buat satu baris EXPIRED per baris EARN.
     *
     * @return array{expired_rows: int, affected_patients: int}
     */
    public function expireAll(): array
    {
        $expiredRows      = 0;
        $affectedPatients = 0;

        // Ambil semua pasien yang punya EARN kedaluwarsa
        $pasienIds = PointTransaction::expireable()
            ->distinct()
            ->pluck('id_pasien');

        foreach ($pasienIds as $idPasien) {
            DB::transaction(function () use ($idPasien, &$expiredRows, &$affectedPatients) {
                $pasien = Pasien::where('id_pasien', $idPasien)->lockForUpdate()->first();

                if (!$pasien) {
                    return;
                }

                // Ambil semua EARN kedaluwarsa milik pasien ini
                $earnRows = PointTransaction::where('id_pasien', $idPasien)
                    ->expireable()
                    ->get();

                if ($earnRows->isEmpty()) {
                    return;
                }

                $totalExpired = $earnRows->sum('amount');
                $newBalance   = max(0, $pasien->points_balance - $totalExpired);

                // Buat record EXPIRED per baris EARN
                foreach ($earnRows as $earn) {
                    PointTransaction::create([
                        'id_pasien'         => $idPasien,
                        'type'              => PointTransaction::TYPE_EXPIRED,
                        'amount'            => $earn->amount,
                        'balance_after'     => $newBalance,
                        'reference_earn_id' => $earn->id,
                        'note'              => "Poin expired dari EARN #{$earn->id} (booking #{$earn->id_booking})",
                    ]);

                    $expiredRows++;
                }

                $pasien->update(['points_balance' => $newBalance]);
                $affectedPatients++;

                Log::info("[PointService] Expire {$totalExpired} poin pasien #{$idPasien}. Balance baru: {$newBalance}.");
            });
        }

        return [
            'expired_rows'      => $expiredRows,
            'affected_patients' => $affectedPatients,
        ];
    }
}
