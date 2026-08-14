<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBank extends Model
{
    use HasFactory;

    protected $table = 'master_bank';
    protected $primaryKey = 'id_bank';

    protected $fillable = [
        'nama_bank',
        'kode_bank',
        'logo_bank',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenagaMedisList()
    {
        return $this->hasMany(TenagaMedis::class, 'id_bank', 'id_bank');
    }
}
