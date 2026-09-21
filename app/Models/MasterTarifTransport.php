<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterTarifTransport extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'master_tarif_transport';
    protected $primaryKey = 'id_transport';

    protected $fillable = [
        'tarif_per_10_km',
    ];

    protected $casts = [
        'tarif_per_10_km' => 'decimal:2',
    ];

}
