<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model PointTransaction
 *
 * Audit trail setiap mutasi poin pasien.
 *
 * type:
 *   EARN    – poin masuk saat booking/transaksi berstatus Selesai/Paid
 *   REDEEM  – poin dipakai pasien untuk diskon (reserved)
 *   EXPIRED – poin hangus karena melewati expired_at
 *
 * @property int         $id
 * @property int         $id_pasien
 * @property int|null    $id_booking
 * @property int|null    $id_transaksi
 * @property string      $type           EARN | REDEEM | EXPIRED
 * @property int         $amount         Jumlah poin (selalu positif)
 * @property int         $balance_after  Saldo setelah mutasi ini
 * @property \Carbon\Carbon|null $expired_at   Kapan poin kedaluwarsa
 * @property int|null    $reference_earn_id  FK ke EARN yang di-expire (untuk EXPIRED)
 * @property string|null $note
 */
class PointTransaction extends Model
{
    protected $table = 'point_transactions';

    // ── Type constants ────────────────────────────────────────────────
    const TYPE_EARN    = 'EARN';
    const TYPE_REDEEM  = 'REDEEM';
    const TYPE_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'id_pasien',
        'id_booking',
        'id_transaksi',
        'type',
        'amount',
        'balance_after',
        'expired_at',
        'reference_earn_id',
        'note',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'balance_after' => 'integer',
        'expired_at'    => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class, 'id_pasien', 'id_pasien');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    /**
     * EARN yang menjadi sumber baris EXPIRED ini.
     */
    public function earnSource(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'reference_earn_id', 'id');
    }

    /**
     * Record EXPIRED yang mereferensikan EARN ini.
     */
    public function expiredRecords(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'reference_earn_id', 'id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Hanya baris tipe EARN. */
    public function scopeEarn($query)
    {
        return $query->where('type', self::TYPE_EARN);
    }

    /** Hanya baris tipe EXPIRED. */
    public function scopeExpiredType($query)
    {
        return $query->where('type', self::TYPE_EXPIRED);
    }

    /**
     * Baris EARN yang sudah lewat expired_at dan belum pernah di-expire.
     * Dipakai oleh ExpirePoints command.
     */
    public function scopeExpireable($query)
    {
        return $query
            ->where('type', self::TYPE_EARN)
            ->where('expired_at', '<=', now())
            ->whereDoesntHave('expiredRecords');
    }
}
