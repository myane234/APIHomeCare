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
 *   - earn()   : Tambah poin saat transaksi/booking selesai
 *   - redeem() : Potong poin saat pasien memakai poin (reserved)
 *   - expireAll() : Hanguskan semua EARN yang sudah melewati expired_at
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
     * Potong poin (REDEEM) saat pasien memakai poin untuk diskon.
     *
     * @param  Pasien    $pasien
     * @param  int       $pointsToRedeem  Jumlah poin yang akan dipakai
     * @param  string    $note            Keterangan (mis. "Diskon booking #xxx")
     * @return PointTransaction
     *
     * @throws \RuntimeException  Jika saldo tidak cukup
     */
    public function redeem(Pasien $pasien, int $pointsToRedeem, string $note = 'Redeem poin'): PointTransaction
    {
        if ($pointsToRedeem <= 0) {
            throw new \InvalidArgumentException('Jumlah poin redeem harus lebih dari 0.');
        }

        return DB::transaction(function () use ($pasien, $pointsToRedeem, $note) {
            $pasien = Pasien::where('id_pasien', $pasien->id_pasien)->lockForUpdate()->first();

            if ($pasien->points_balance < $pointsToRedeem) {
                throw new \RuntimeException(
                    "Saldo poin tidak cukup. Tersedia: {$pasien->points_balance}, diminta: {$pointsToRedeem}."
                );
            }

            $newBalance = $pasien->points_balance - $pointsToRedeem;

            $pt = PointTransaction::create([
                'id_pasien'    => $pasien->id_pasien,
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
                $now = now();
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
