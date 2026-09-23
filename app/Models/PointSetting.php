<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Model PointSetting
 *
 * Single-row config untuk fitur poin pasien.
 * Selalu gunakan PointSetting::current() untuk membaca setting aktif.
 *
 * @property int     $id
 * @property int     $point_rate                  Nominal Rp untuk mendapatkan 1 poin (default 10000)
 * @property int     $point_expiry_days           Masa berlaku poin dalam hari (default 365)
 * @property bool    $is_active                   Apakah fitur poin aktif
 * @property int     $max_point_discount_percent  Persentase maks dari total tagihan yang bisa dipotong poin (default 50)
 * @property string|null $updated_by
 */
class PointSetting extends Model
{
    protected $table = 'point_settings';

    protected $fillable = [
        'point_rate',
        'point_expiry_days',
        'is_active',
        'max_point_discount_percent',
        'updated_by',
    ];

    protected $casts = [
        'point_rate'                 => 'integer',
        'point_expiry_days'          => 'integer',
        'is_active'                  => 'boolean',
        'max_point_discount_percent' => 'integer',
    ];

    // ── Cache key ─────────────────────────────────────────────────────

    const CACHE_KEY = 'point_setting';

    // ── Static helpers ────────────────────────────────────────────────

    /**
     * Ambil setting aktif (selalu row id = 1).
     * Di-cache selamanya; di-invalidate saat save().
     */
    public static function current(): static
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::firstOrCreate(
                ['id' => 1],
                [
                    'point_rate'                 => 10000,
                    'point_expiry_days'          => 365,
                    'is_active'                  => true,
                    'max_point_discount_percent' => 50,
                ]
            );
        });
    }

    /**
     * Hitung poin yang didapat dari suatu nominal transaksi.
     * Rumus: floor(jumlah / point_rate)
     *
     * @param  int|float $jumlah  Nominal transaksi dalam Rupiah
     * @return int                Poin yang diperoleh (≥ 0)
     */
    public static function calculateEarn(int|float $jumlah): int
    {
        $setting = static::current();

        if (!$setting->is_active || $setting->point_rate <= 0) {
            return 0;
        }

        return (int) floor($jumlah / $setting->point_rate);
    }

    /**
     * Hitung berapa poin maksimal yang bisa di-redeem untuk suatu total tagihan.
     *
     * Aturan:
     *   1. Fitur poin harus aktif.
     *   2. 1 poin = Rp 1 → diskon (Rp) = jumlah poin yang dipakai.
     *   3. Diskon maks = max_point_discount_percent % dari total tagihan.
     *   4. Tidak boleh melebihi saldo poin pasien.
     *   5. Tidak boleh membuat tagihan menjadi 0 atau negatif (minimal bayar Rp 1).
     *
     * @param  int|float $totalTagihan    Total tagihan sebelum diskon poin (Rp)
     * @param  int       $pointsBalance   Saldo poin pasien saat ini
     * @return array{
     *     max_points_redeemable: int,
     *     max_discount_rp: int,
     *     max_discount_percent: int,
     *     is_active: bool
     * }
     */
    public static function calculateMaxRedeemablePoints(int|float $totalTagihan, int $pointsBalance): array
    {
        $setting = static::current();

        if (!$setting->is_active || $totalTagihan <= 0) {
            return [
                'max_points_redeemable' => 0,
                'max_discount_rp'       => 0,
                'max_discount_percent'  => $setting->max_point_discount_percent,
                'is_active'             => $setting->is_active,
            ];
        }

        // Maks diskon berdasarkan persentase setting
        $maxDiscountRp = (int) floor($totalTagihan * ($setting->max_point_discount_percent / 100));

        // Karena 1 poin = Rp 1, maks poin = maks diskon (Rp)
        // Tidak boleh melebihi saldo pasien
        $maxPointsRedeemable = min($maxDiscountRp, $pointsBalance);

        // Pastikan tagihan tidak menjadi 0 (minimal Rp 1 harus dibayar)
        $maxPointsRedeemable = min($maxPointsRedeemable, (int) $totalTagihan - 1);
        $maxPointsRedeemable = max(0, $maxPointsRedeemable);

        return [
            'max_points_redeemable' => $maxPointsRedeemable,
            'max_discount_rp'       => $maxPointsRedeemable, // 1 poin = Rp 1
            'max_discount_percent'  => $setting->max_point_discount_percent,
            'is_active'             => $setting->is_active,
        ];
    }

    // ── Override save untuk invalidate cache ─────────────────────────

    /**
     * Setelah save, buang cache agar setting terbaru langsung terbaca.
     */
    public function save(array $options = []): bool
    {
        $result = parent::save($options);
        Cache::forget(self::CACHE_KEY);
        return $result;
    }
}
