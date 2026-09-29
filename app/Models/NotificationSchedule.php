<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'notification_schedules';

    protected $fillable = [
        'template_id',
        'cron_expression',
        'delay_unit',
        'delay_value',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'delay_value' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
