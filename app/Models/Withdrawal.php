<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $table      = 'withdrawals';
    protected $primaryKey = 'id_withdrawal';

    protected $fillable = [
        'id_tenaga_medis',
        'jumlah',
        'status',

        // Snapshot rekening
        'beneficiary_name',
        'beneficiary_account',
        'beneficiary_bank',
        'beneficiary_bank_name',

        // Data Iris
        'iris_reference_no',
        'iris_response',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'jumlah'        => 'decimal:2',
        'iris_response' => 'array',
        'processed_at'  => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Konstanta Status
    // ──────────────────────────────────────────────────────────────────────

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS    = 'success';
    const STATUS_FAILED     = 'failed';

    const MINIMUM_WITHDRAW = 50000; // Rp 50.000

    // ──────────────────────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────────────────────

    public function tenagaMedis()
    {
        return $this->belongsTo(TenagaMedis::class, 'id_tenaga_medis', 'id_tenaga_medis');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Apakah withdraw masih dalam proses (saldo sudah di-hold).
     */
    public function isOngoing(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING    => 'Menunggu',
            self::STATUS_PROCESSING => 'Diproses',
            self::STATUS_SUCCESS    => 'Berhasil',
            self::STATUS_FAILED     => 'Gagal',
            default                 => ucfirst($this->status),
        };
    }

    /**
     * Format nominal ke Rupiah.
     */
    public function jumlahFormat(): string
    {
        return 'Rp ' . number_format((float) $this->jumlah, 0, ',', '.');
    }
}
