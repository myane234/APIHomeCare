<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterBank extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_bank';
    protected $primaryKey = 'id_bank';

    protected $fillable = [
        'nama_bank',
        'kode_bank',
        'gambar',
        'is_active',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenagaMedisList()
    {
        return $this->hasMany(TenagaMedis::class, 'id_bank', 'id_bank');
    }
}
