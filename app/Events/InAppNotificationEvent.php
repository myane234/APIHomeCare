<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InAppNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Data notifikasi yang akan di-broadcast.
     */
    public array $payload;

    /**
     * ID user penerima.
     */
    public int $userId;

    /**
     * Role user penerima.
     */
    public string $userRole;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification)
    {
        $this->userId   = $notification->user_id;
        $this->userRole = $notification->user_role;

        $this->payload = [
            'id'         => $notification->id,
            'title'      => $notification->title,
            'body'       => $notification->body,
            'action_url' => $notification->action_url,
            'data'       => $notification->data,
            'is_read'    => $notification->is_read,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Channel private per-user: notifications.{user_role}.{user_id}
     * Contoh: notifications.pasien.42
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("notifications.{$this->userRole}.{$this->userId}"),
        ];
    }

    /**
     * Nama event yang diterima di sisi klien.
     */
    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    /**
     * Data yang dikirim dalam broadcast payload.
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
