<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GlobalConfig extends Model
{
    use HasFactory;

    protected $table = 'global_configs';

    protected $fillable = [
        'app_name',
        'app_logo',
        'app_favicon',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'whatsapp_number',
        'phone_number',
        'email',
        'address',
        'socials',
        'maintenance_mode',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'socials' => 'array',
    ];

    /**
     * Get logo full URL
     */
    public function getAppLogoUrlAttribute()
    {
        return $this->app_logo ? url(Storage::url($this->app_logo)) : null;
    }

    /**
     * Get favicon full URL
     */
    public function getAppFaviconUrlAttribute()
    {
        return $this->app_favicon ? url(Storage::url($this->app_favicon)) : null;
    }
}
