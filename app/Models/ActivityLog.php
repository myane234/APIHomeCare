<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';
    protected $primaryKey = 'id_log';

    protected $fillable = [
        'user_id',
        'user_type',
        'user_name',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    /**
     * Helper static method untuk mencatat log aktivitas dengan mudah
     *
     * @param string $action
     * @param string|null $description
     * @param mixed|null $user
     * @param string $userType
     * @return ActivityLog
     */
    public static function log(string $action, ?string $description = null, $user = null, string $userType = 'Admin'): self
    {
        $currentUser = $user ?? request()->user();

        $userId = null;
        $userName = 'System / Guest';

        if ($currentUser) {
            $userId = $currentUser->id_admin ?? $currentUser->id_pasien ?? $currentUser->id_tenaga_medis ?? $currentUser->id ?? null;
            $userName = $currentUser->nama_lengkap ?? $currentUser->name ?? $currentUser->email ?? 'User #' . $userId;
        }

        return self::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'user_name' => $userName,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
