<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelurahan extends Model
{
    use HasFactory;

    protected $table = 'master_kelurahan';
    protected $primaryKey = 'id_kelurahan';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kelurahan',
        'id_kecamatan',
        'nama_kelurahan',
    ];



    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kecamatan', 'id_kecamatan');
    }
}
