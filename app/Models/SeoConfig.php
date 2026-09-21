<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoConfig extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'seo_configs';

    protected $fillable = [
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];
}
