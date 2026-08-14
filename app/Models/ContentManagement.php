<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ContentManagement extends Model
{
    use HasFactory;

    protected $table = 'content_managements';

    protected $fillable = [
        'home_banner',
        'home_text_banner',
        'home_description',
        'promo_heading',
        'promo_text',
        'artikel_heading',
        'artikel_text',
        'layanan_heading',
        'layanan_text',
        'about_banner',
        'about_text_banner',
        'about_description_text',
        'about_description_image',
        'visi_misi',
        'cara_kerja',
        'wilayah_layanan',
        'komitmen',
    ];

    public function getHomeBannerUrlAttribute()
    {
        return $this->home_banner ? url(Storage::url($this->home_banner)) : null;
    }

    public function getAboutBannerUrlAttribute()
    {
        return $this->about_banner ? url(Storage::url($this->about_banner)) : null;
    }

    public function getAboutDescriptionImageUrlAttribute()
    {
        return $this->about_description_image ? url(Storage::url($this->about_description_image)) : null;
    }
}
