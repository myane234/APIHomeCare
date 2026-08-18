<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $table = 'notification_templates';

    protected $fillable = [
        'code',
        'name',
        'title',
        'body',
        'channel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
