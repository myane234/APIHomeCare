<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriArtikel extends Model
{
    use \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'kategori_artikels';
    protected $primaryKey = 'id_kategori_artikel';
    protected $fillable = ['nama_kategori'];
}
