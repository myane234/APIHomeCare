<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_tag';

    protected $fillable = [
        'nama_tag',
        'slug',
    ];

    /**
     * Boot function untuk auto-generate slug jika tidak diisi.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->nama_tag);
            }
        });

        static::updating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->nama_tag);
            }
        });
    }

    /**
     * Relasi many-to-many ke Artikel.
     */
    public function artikels()
    {
        return $this->belongsToMany(Artikel::class, 'artikel_tags', 'tag_id', 'artikel_id')->withTimestamps();
    }
}
