<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminTier extends Model
{
    use HasFactory;

    protected $table = 'admin_tiers';
    protected $primaryKey = 'id_admin_tier'; // Menyesuaikan nama primary key

    protected $fillable = [
        'nama_tier',
        'slug',
        'deskripsi',
        'is_protected',
    ];
}