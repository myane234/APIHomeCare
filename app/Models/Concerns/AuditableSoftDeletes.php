<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

trait AuditableSoftDeletes
{
    use SoftDeletes;

    public static function bootAuditableSoftDeletes(): void
    {
        static::creating(function ($model): void {
            $actorId = self::actorId();
            if ($actorId !== null && self::hasColumn($model, 'created_by') && $model->created_by === null) {
                $model->created_by = $actorId;
            }
            if ($actorId !== null && self::hasColumn($model, 'updated_by')) {
                $model->updated_by = $actorId;
            }
        });

        static::updating(function ($model): void {
            $actorId = self::actorId();
            if ($actorId !== null && self::hasColumn($model, 'updated_by')) {
                $model->updated_by = $actorId;
            }
        });

        static::deleting(function ($model): void {
            $actorId = self::actorId();
            if ($actorId !== null && self::hasColumn($model, 'deleted_by')) {
                $model->deleted_by = $actorId;
                $model->saveQuietly();
            }
        });
    }

    private static function actorId(): ?int
    {
        try {
            $user = request()->user();
            return $user ? (int) $user->getKey() : null;
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
