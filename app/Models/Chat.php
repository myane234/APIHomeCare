<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory, \App\Models\Concerns\AuditableSoftDeletes;

    protected $table = 'chats';
    protected $primaryKey = 'id_chat';

    protected $fillable = [
        'id_booking',
        'id_pengirim',
        'pesan',
        'waktu_kirim',
    ];

    protected $casts = [
        'waktu_kirim' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    public function pengirim()
    {
        return $this->belongsTo(Users::class, 'id_pengirim', 'id_user');
    }
}
