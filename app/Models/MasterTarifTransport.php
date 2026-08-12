<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterTarifTransport extends Model
{
    use HasFactory;

    protected $table = 'master_tarif_transport';
    protected $primaryKey = 'id_transport';

    protected $fillable = [
        'id_kota',
        'tarif_awal',
        'tarif_per_kilometer',
    ];

    public function kota()
    {
        return $this->belongsTo(KotaKabupaten::class, 'id_kota', 'id_kota');
    }
}
