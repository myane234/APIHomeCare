<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'notification_templates';

    protected $fillable = [
        'code',
        'name',
        'target_role',
        'trigger_type',
        'title',
        'body',
        'action_url',
        'channel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function schedules()
    {
        return $this->hasMany(NotificationSchedule::class, 'template_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'template_id');
    }
}
