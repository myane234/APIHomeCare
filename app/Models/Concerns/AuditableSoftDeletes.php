<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

trait AuditableSoftDeletes
{
    use SoftDeletes;

    public static function bootAuditableSoftDeletes(): void
    {
        static::creating(function ($model): void {
            $actor = self::actorName();
            if ($actor !== null && self::hasColumn($model, 'created_by') && $model->created_by === null) {
                $model->created_by = $actor;
            }
            if ($actor !== null && self::hasColumn($model, 'updated_by')) {
                $model->updated_by = $actor;
            }
        });

        static::updating(function ($model): void {
            $actor = self::actorName();
            if ($actor !== null && self::hasColumn($model, 'updated_by')) {
                $model->updated_by = $actor;
            }
        });

        static::deleting(function ($model): void {
            $actor = self::actorName();
            if ($actor !== null && self::hasColumn($model, 'deleted_by')) {
                $model->deleted_by = $actor;
                $model->saveQuietly();
            }
        });
    }

    /**
     * Resolve nama aktor dari user yang sedang login.
     *
     * Urutan prioritas:
     *   1. Admin       → nama_lengkap
     *   2. TenagaMedis → nama_lengkap (via relasi)
     *   3. Pasien      → nama_lengkap (via relasi)
     *   4. Users       → email (fallback jika tidak ada relasi)
     *
     * @return string|null
     */
    private static function actorName(): ?string
    {
        try {
            $user = request()->user();

            if (!$user) {
                return null;
            }

            // Guard: Admin (model App\Models\Admin)
            if ($user instanceof \App\Models\Admin) {
                return $user->nama_lengkap ?? $user->email;
            }

            // Guard: Users — coba ambil nama dari relasi
            if ($user instanceof \App\Models\Users) {
                // TenagaMedis memiliki nama_lengkap
                if ($user->relationLoaded('tenagaMedis') && $user->tenagaMedis) {
                    return $user->tenagaMedis->nama_lengkap;
                }

                // Lazy-load tenagaMedis jika belum di-load
                $tenagaMedis = $user->tenagaMedis()->first();
                if ($tenagaMedis) {
                    return $tenagaMedis->nama_lengkap;
                }

                // Pasien memiliki nama_lengkap
                if ($user->relationLoaded('pasien') && $user->pasien) {
                    return $user->pasien->nama_lengkap;
                }

                $pasien = $user->pasien()->first();
                if ($pasien) {
                    return $pasien->nama_lengkap;
                }

                // Fallback: email
                return $user->email;
            }

            // Fallback generik: coba properti umum
            return $user->nama_lengkap
                ?? $user->name
                ?? $user->email
                ?? (string) $user->getKey();

        } catch (\Throwable) {
            return null;
        }
    }

    private static function hasColumn($model, string $column): bool
    {
        return array_key_exists($column, $model->getAttributes()) ||
            $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
