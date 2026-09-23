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
 * @property int     $point_rate         Nominal Rp untuk 1 poin (default 10000)
 * @property int     $point_expiry_days  Masa berlaku poin dalam hari (default 365)
 * @property bool    $is_active          Apakah fitur poin aktif
 * @property string|null $updated_by
 */
class PointSetting extends Model
{
    protected $table = 'point_settings';

    protected $fillable = [
        'point_rate',
        'point_expiry_days',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'point_rate'        => 'integer',
        'point_expiry_days' => 'integer',
        'is_active'         => 'boolean',
    ];

    // ── Cache key ─────────────────────────────────────────────────────

    const CACHE_KEY = 'point_setting';

    // ── Static helpers ────────────────────────────────────────────────

    /**
     * Ambil setting aktif (selalu row id = 1).
     * Di-cache selamanya; di-invalidate saat update().
     */
    public static function current(): static
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::firstOrCreate(
                ['id' => 1],
                [
                    'point_rate'        => 10000,
                    'point_expiry_days' => 365,
                    'is_active'         => true,
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

    // ── Override update untuk invalidate cache ───────────────────────

    /**
     * Setelah update, buang cache agar setting terbaru langsung terbaca.
     */
    public function save(array $options = []): bool
    {
        $result = parent::save($options);
        Cache::forget(self::CACHE_KEY);
        return $result;
    }
}
