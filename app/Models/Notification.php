<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'user_role',
        'template_id',
        'title',
        'body',
        'action_url',
        'data',
        'is_read',
        'read_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data'     => 'array',
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope untuk filter notifikasi milik user tertentu berdasarkan role.
     */
    public function scopeForUser($query, int $userId, string $userRole)
    {
        return $query->where('user_id', $userId)
                     ->where('user_role', $userRole);
    }

    /**
     * Scope unread saja.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
