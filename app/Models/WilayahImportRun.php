<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WilayahImportRun extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'status',
        'total_provinces',
        'processed_provinces',
        'processed_cities',
        'processed_districts',
        'processed_villages',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->id ??= (string) Str::uuid();
        });
    }

    public function logs()
    {
        return $this->hasMany(WilayahImportLog::class, 'run_id')->orderBy('id');
    }
}
