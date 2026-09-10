<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Chat;
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
    private string $wsServerHost;
    private string $wsServerPort;

    public function __construct()
    {
        $this->wsServerHost = env('WEBSOCKET_HOST', '192.168.18.12');
        $this->wsServerPort = (string) env('WEBSOCKET_PORT', '8088');
    }

    /**
     * API Admin: List all chat rooms with pagination & filters
     * GET /api/admin/chat-rooms or /api/manage-admin/chat-rooms
     */
    public function adminRooms(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', $request->query('limit', 15)));
        $page = max(1, (int) $request->query('page', 1));
        $status = $request->query('status');
        $search = $request->query('search');

        $query = Booking::with(['pasien', 'tenagaMedis', 'layanan'])
            ->where('status_booking', '!=', 'Dibatalkan');

        if ($status) {
            $query->where('status_booking', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhereHas('pasien', function ($qp) use ($search) {
                        $qp->where('nama_lengkap', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tenagaMedis', function ($qn) use ($search) {
                        $qn->where('nama_lengkap', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderByDesc('updated_at')->paginate($perPage, ['*'], 'page', $page);

        // Ambil status rooms aktif dari Go WebSocket Server
        $goRooms = [];
        try {
            $goRes = Http::timeout(2)->get($this->goHttpUrl('/rooms'));
            if ($goRes->successful()) {
                $goRooms = collect($goRes->json('rooms', []))->keyBy('booking_id')->all();
            }
        } catch (\Throwable $e) {
            // Ignore Go timeout
        }

        $formattedData = collect($paginator->items())->map(function ($booking) use ($goRooms) {
            $bookingId = (int) $booking->id_booking;
            $goRoom = $goRooms[$bookingId] ?? null;

            $lastChat = Chat::where('id_booking', $bookingId)->latest('waktu_kirim')->first();
            $totalChats = Chat::where('id_booking', $bookingId)->count();

            return [
                'booking_id' => $bookingId,
                'booking_code' => $booking->booking_code,
                'status_booking' => $booking->status_booking,
                'tanggal_kunjungan' => $booking->tanggal_kunjungan,
                'jam_kunjungan' => $booking->jam_kunjungan,
                'pasien' => $booking->pasien ? [
                    'id' => (int) $booking->pasien->id_pasien,
                    'name' => $booking->pasien->nama_lengkap,
                    'no_telp' => $booking->pasien->no_telp,
                    'foto_profile' => $booking->pasien->foto_profile,
                ] : null,
                'nakes' => $booking->tenagaMedis ? [
                    'id' => (int) $booking->tenagaMedis->id_tenaga_medis,
                    'name' => $booking->tenagaMedis->nama_lengkap,
                    'jenis_tenaga_medis' => $booking->tenagaMedis->jenis_tenaga_medis,
                    'no_telp' => $booking->tenagaMedis->no_telp,
                    'foto_profile' => $booking->tenagaMedis->foto_profile,
                ] : null,
                'layanan' => $booking->layanan ? [
                    'id' => (int) $booking->layanan->id_layanan,
                    'nama_layanan' => $booking->layanan->nama_layanan,
                ] : null,
                'websocket_active' => $goRoom !== null,
                'client_count' => $goRoom['client_count'] ?? 0,
                'total_messages' => $totalChats,
                'last_message' => $lastChat ? [
                    'id_chat' => (int) $lastChat->id_chat,
                    'sender_id' => (int) $lastChat->id_pengirim,
                    'content' => $lastChat->pesan,
                    'timestamp' => $lastChat->waktu_kirim?->toIso8601String(),
                ] : null,
                'created_at' => $booking->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar chat rooms berhasil diambil.',
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * API Admin: Detail Chat Room & Riwayat Pesan per Booking ID
     * GET /api/admin/chat-rooms/{id} or /api/manage-admin/chat-rooms/{id}
     */
    public function adminRoomDetail(Request $request, $id)
    {
        $booking = Booking::with(['pasien', 'tenagaMedis', 'layanan', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.',
            ], 404);
        }

        $chats = Chat::where('id_booking', $id)
            ->with(['pengirim.pasien', 'pengirim.tenagaMedis'])
            ->orderBy('waktu_kirim', 'asc')
            ->get();

        $messages = $chats->map(function ($chat) use ($booking) {
            $senderUser = $chat->pengirim;
            $senderType = 'user';
            $senderName = 'User';

            if ($booking->tenagaMedis && $senderUser && (int) $senderUser->id_user === (int) $booking->tenagaMedis->id_user) {
                $senderType = 'nakes';
                $senderName = $booking->tenagaMedis->nama_lengkap;
            } elseif ($booking->pasien && $senderUser && (int) $senderUser->id_user === (int) $booking->pasien->id_user) {
                $senderType = 'pasien';
                $senderName = $booking->pasien->nama_lengkap;
            } elseif ($senderUser) {
                $senderName = $senderUser->name ?: 'Admin';
                $senderType = 'admin';
            }

            return [
                'id_chat' => (int) $chat->id_chat,
                'booking_id' => (int) $chat->id_booking,
                'sender_id' => (int) $chat->id_pengirim,
                'sender_type' => $senderType,
                'sender_name' => $senderName,
                'content' => $chat->pesan,
                'timestamp' => $chat->waktu_kirim?->toIso8601String() ?: $chat->created_at?->toIso8601String(),
            ];
        });

        $goRoomInfo = null;
        try {
            $goRes = Http::timeout(2)->get($this->goHttpUrl('/rooms'));
            if ($goRes->successful()) {
                $rooms = collect($goRes->json('rooms', []))->keyBy('booking_id');
                $goRoomInfo = $rooms->get((int) $id);
            }
        } catch (\Throwable $e) {
            // Ignore Go connection errors
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail chat room berhasil diambil.',
            'data' => [
                'room_info' => [
                    'booking_id' => (int) $booking->id_booking,
                    'booking_code' => $booking->booking_code,
                    'status_booking' => $booking->status_booking,
                    'tanggal_kunjungan' => $booking->tanggal_kunjungan,
                    'jam_kunjungan' => $booking->jam_kunjungan,
                    'alamat_kunjungan' => $booking->alamat_kunjungan,
                    'pasien' => $booking->pasien ? [
                        'id' => (int) $booking->pasien->id_pasien,
                        'name' => $booking->pasien->nama_lengkap,
                        'no_telp' => $booking->pasien->no_telp,
                        'foto_profile' => $booking->pasien->foto_profile,
                    ] : null,
                    'nakes' => $booking->tenagaMedis ? [
                        'id' => (int) $booking->tenagaMedis->id_tenaga_medis,
                        'name' => $booking->tenagaMedis->nama_lengkap,
                        'jenis_tenaga_medis' => $booking->tenagaMedis->jenis_tenaga_medis,
                        'no_telp' => $booking->tenagaMedis->no_telp,
                        'foto_profile' => $booking->tenagaMedis->foto_profile,
                    ] : null,
                    'layanan' => $booking->layanan ? [
                        'id' => (int) $booking->layanan->id_layanan,
                        'nama_layanan' => $booking->layanan->nama_layanan,
                    ] : null,
                    'websocket_connected' => $goRoomInfo !== null,
                    'websocket_client_count' => $goRoomInfo['client_count'] ?? 0,
                    'created_at' => $booking->created_at?->toIso8601String(),
                ],
                'total_messages' => $messages->count(),
                'messages' => $messages,
            ],
        ]);
    }

    public function ensureChatRoom(Booking $booking): bool
    {
        try {
            $booking->loadMissing(['pasien', 'tenagaMedis']);

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

        $user = $request->user();
        if ($user) {
            Chat::create([
                'id_booking' => (int) $id,
                'id_pengirim' => (int) ($user->id_user ?? $user->id),
                'pesan' => $request->input('content'),
                'waktu_kirim' => now(),
            ]);
        }

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
