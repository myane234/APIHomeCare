<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    use HasFactory;

    protected $table = 'password_reset_otps';

    protected $fillable = [
        'email',
        'user_type',
        'otp',
        'otp_expires_at',
        'reset_token',
        'token_expires_at',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];
}
