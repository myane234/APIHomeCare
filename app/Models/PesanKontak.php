<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PesanKontak extends Model
{
    use HasFactory;

    protected $table = 'pesan_kontaks';

    protected $fillable = [
        'nama',
        'email',
        'no_hp',
        'subjek',
        'pesan',
        'status',
        'catatan_admin',
    ];
}
