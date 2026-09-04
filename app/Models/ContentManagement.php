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
        
        // Gabung Mitra
        'mitra_banner',
        'mitra_text_banner',
        'mitra_description',

        // Footer
        'footer_description',
        'footer_phone',
        'footer_email',
        'footer_address',
        'footer_socials',
        'footer_links',

        // Ulasan Section Header
        'ulasan_heading',
        'ulasan_subheading',

        // Hubungi Kami Page Content
        'hubungi_banner',
        'hubungi_banner_text',
        'hubungi_heading',
        'hubungi_description',
        'hubungi_phone',
        'hubungi_email',
        'hubungi_whatsapp',
        'hubungi_address',
        'hubungi_maps_link',
        'hubungi_jam_operasional',
    ];

    protected $casts = [
        'footer_socials' => 'array',
        'footer_links' => 'array',
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

    public function getMitraBannerUrlAttribute()
    {
        return $this->mitra_banner ? url(Storage::url($this->mitra_banner)) : null;
    }

    public function getHubungiBannerUrlAttribute()
    {
        return $this->hubungi_banner ? url(Storage::url($this->hubungi_banner)) : null;
    }
}
