<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @group WS 
 * Controller untuk integrasi WebSocket Service Go (192.168.18.12:8088)
 */
class WebSocketController extends Controller
{
    private string $wsServerHost = '192.168.18.12';
    private string $wsServerPort = '8088';

    public function adminRooms(Request $request)
    {
        try {
            $response = Http::timeout(3)->get($this->goHttpUrl('/rooms'), $request->query());
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil room dari Go WebSocket server: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Go WebSocket server tidak dapat dihubungi.'], 503);
        }
    }

    public function ensureChatRoom(Booking $booking): bool
    {
        try {
            $response = Http::timeout(3)->post($this->goHttpUrl('/rooms'), [
                'booking_id' => (int) $booking->id_booking,
                'pasien' => $booking->pasien ? ['id' => (int) $booking->pasien->id_pasien, 'name' => $booking->pasien->nama_lengkap] : null,
                'nakes' => $booking->tenagaMedis ? ['id' => (int) $booking->tenagaMedis->id_tenaga_medis, 'name' => $booking->tenagaMedis->nama_lengkap] : null,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat room di Go WebSocket server: ' . $e->getMessage());
            return false;
        }
    }

    public function closeChatRoom($id)
    {
        if (!Booking::find($id)) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan.'], 404);
        }

        try {
            $response = Http::timeout(3)->delete($this->goHttpUrl('/rooms/' . (int) $id));

            return response()->json([
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Room chat berhasil ditutup.'
                    : 'Room chat tidak ditemukan atau sudah ditutup.',
                'data' => $response->json(),
            ], $response->successful() ? 200 : $response->status());
        } catch (\Throwable $e) {
            Log::warning('Gagal menutup room di Go WebSocket server: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Go WebSocket server tidak dapat dihubungi.'], 503);
        }
    }

    /**
     * API Ambil Konfigurasi WebSocket Connection untuk Mobile/Frontend Client
     * GET /api/websocket/config
     */
    public function getConfig(Request $request)
    {
        $bookingId = $request->query('booking_id');

        [$userType, $userId] = $this->resolveSender($request);

        $wsUrl = $this->buildWsUrl($bookingId, $userId, $userType);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi WebSocket Service',
            'data' => [
                'ws_host' => $this->wsServerHost,
                'ws_port' => (int) $this->wsServerPort,
                'ws_url' => $wsUrl,
                'broadcast_url' => "http://{$this->wsServerHost}:{$this->wsServerPort}/broadcast",
                'user_info' => [
                    'user_id' => (int) $userId,
                    'user_type' => $userType,
                    'booking_id' => $bookingId ? (int) $bookingId : null,
                ],
            ],
        ]);
    }

    /**
     * API Kirim Chat Pesan via Backend Laravel (Diteruskan ke Server Go WebSocket)
     * POST /api/booking/{id}/chat
     */
    public function sendChatMessage(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan.'], 404);
        }

        [$senderType, $senderId, $senderName] = $this->resolveSender($request, true);

        $payload = [
            'type' => 'chat_message',
            'booking_id' => (int) $id,
            'sender_id' => (int) $senderId,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'content' => $request->input('content'),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->ensureChatRoom($booking->load(['pasien', 'tenagaMedis']));

        // Broadcast ke Server Go WebSocket
        $broadcastSuccess = $this->triggerGoBroadcast($payload);

        return response()->json([
            'success' => true,
            'message' => 'Pesan chat berhasil dikirim.',
            'data' => [
                'message' => $payload,
                'websocket_broadcast' => $broadcastSuccess,
            ],
        ]);
    }

    /**
     * API Update Lokasi Realtime Nakes (Disimpan ke DB & Diteruskan ke Go WebSocket Server)
     * POST /api/nakes/update-lokasi
     */
    public function updateNakesLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'booking_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $nakes = null;

        if ($user instanceof TenagaMedis) {
            $nakes = $user;
        } elseif (isset($user->id_tenaga_medis)) {
            $nakes = TenagaMedis::find($user->id_tenaga_medis);
        } else {
            $nakes = TenagaMedis::where('id_user', $user->id_user ?? $user->id)->first();
        }

        if (!$nakes) {
            return response()->json(['success' => false, 'message' => 'Profil Tenaga Medis tidak ditemukan.'], 404);
        }

        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');

        // 1. Update lokasi di DB TenagaMedis
        $nakes->latitude = $lat;
        $nakes->longitude = $lng;
        $nakes->save();

        // 2. Jika ada booking_id aktif, kirim broadcast location_update ke Go WS
        $bookingId = $request->input('booking_id');
        if (!$bookingId) {
            $activeBooking = Booking::where('id_tenaga_medis', $nakes->id_tenaga_medis)
                ->whereIn('status_booking', ['DiPerjalanan', 'Tindakan'])
                ->orderByDesc('created_at')
                ->first();
            $bookingId = $activeBooking?->id_booking;
        }

        $broadcastSuccess = false;
        if ($bookingId) {
            $payload = [
                'type' => 'location_update',
                'booking_id' => (int) $bookingId,
                'sender_id' => (int) $nakes->id_tenaga_medis,
                'sender_type' => 'nakes',
                'sender_name' => $nakes->nama_lengkap,
                'latitude' => $lat,
                'longitude' => $lng,
                'timestamp' => now()->toIso8601String(),
            ];
            $broadcastSuccess = $this->triggerGoBroadcast($payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lokasi Nakes berhasil diperbarui.',
            'data' => [
                'id_tenaga_medis' => $nakes->id_tenaga_medis,
                'latitude' => $lat,
                'longitude' => $lng,
                'booking_id' => $bookingId ? (int) $bookingId : null,
                'websocket_broadcast' => $broadcastSuccess,
            ],
        ]);
    }

    /**
     * Trigger HTTP POST ke Go WebSocket Server broadcast endpoint
     */
    private function triggerGoBroadcast(array $payload): bool
    {
        try {
            $url = "http://{$this->wsServerHost}:{$this->wsServerPort}/broadcast";
            $response = Http::timeout(3)->post($url, $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Gagal broadcast ke Go WebSocket server: " . $e->getMessage());
            return false;
        }
    }

    private function goHttpUrl(string $path): string
    {
        return "http://{$this->wsServerHost}:{$this->wsServerPort}" . $path;
    }

    private function resolveSender(Request $request, bool $includeName = false): array
    {
        $user = $request->user();
        $nakes = $user instanceof TenagaMedis
            ? $user
            : $user?->tenagaMedis;
        $isNakes = $user instanceof TenagaMedis
            || ($nakes && $user?->roles?->contains('nama_role', 'nakes'));

        if ($isNakes && $nakes) {
            $sender = [
                'nakes',
                (int) $nakes->id_tenaga_medis,
            ];

            return $includeName
                ? [...$sender, $nakes->nama_lengkap ?: 'Nakes']
                : $sender;
        }

        $pasien = $user?->pasien;
        $sender = [
            'pasien',
            (int) ($pasien?->id_pasien ?? $user?->id_user ?? $user?->id ?? 0),
        ];

        return $includeName
            ? [...$sender, $pasien?->nama_lengkap ?: $user?->name ?: 'Pasien']
            : $sender;
    }

    private function buildWsUrl($bookingId, $userId = null, ?string $userType = null): string
    {
        $wsUrl = "ws://{$this->wsServerHost}:{$this->wsServerPort}/ws";
        if ($bookingId) {
            $parameters = ['booking_id' => (int) $bookingId];
            if ($userId !== null && $userType !== null) {
                $parameters['user_id'] = (int) $userId;
                $parameters['user_type'] = $userType;
            }
            $wsUrl .= '?' . http_build_query($parameters);
        }

        return $wsUrl;
    }
}
