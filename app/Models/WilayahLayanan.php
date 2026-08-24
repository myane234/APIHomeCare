<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Model;

class WilayahLayanan extends Model
{
    protected $table = 'master_provinsi';
    protected $primaryKey = 'id_provinsi';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_provinsi',
        'nama_provinsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function kota(): HasMany
    {
        return $this->hasMany(KotaKabupaten::class, 'id_provinsi', 'id_provinsi');
    }
}