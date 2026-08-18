<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admins';
    protected $primaryKey = 'id_admin';

    protected $fillable = [
        'nama_lengkap',
        'email',
        'password',
        'is_active',
        'tier_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function tier()
    {
        return $this->belongsTo(AdminTier::class, 'tier_admin', 'nama_tier');
    }
}
