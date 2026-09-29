<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    protected $fillable = [
        'user_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'trigger_type',
        'template_id',
        'reference_id',
        'reference_type',
        'status',
        'error_message',
        'payload',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id_user');
    }

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
