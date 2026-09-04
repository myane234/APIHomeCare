<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Transaction;
use App\Http\Resources\BookingResource;
use App\Http\Resources\TransaksiDetailResource;

class TransaksiController extends Controller
{
    /**
     * Daftar Transaksi Pasien
     *
     * Menampilkan daftar transaksi/booking pasien yang login dengan pagination dan filter.
     *
     * @group Transaksi
     * @authenticated
     *
     * @queryParam page integer Halaman yang diminta. Default: 1. Example: 1
     * @queryParam per_page integer Jumlah data per halaman. Default: 10, Max: 100. Example: 15
     * @queryParam status_booking string Filter status booking. Values: Pending, DiPerjalanan, Tindakan, Selesai, Dibatalkan. Example: Selesai
     * @queryParam tanggal_dari date Filter dari tanggal (Y-m-d). Example: 2026-09-01
     * @queryParam tanggal_sampai date Filter sampai tanggal (Y-m-d). Example: 2026-09-30
     * @queryParam sort_by string Urutkan by. Values: created_at, tanggal_kunjungan, status_booking. Default: created_at. Example: tanggal_kunjungan
     * @queryParam sort_order string Arah urutan. Values: asc, desc. Default: desc. Example: asc
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Daftar booking pasien",
     *   "pagination": {
     *     "total": 42,
     *     "count": 10,
     *     "per_page": 10,
     *     "current_page": 1,
     *     "total_pages": 5,
     *     "has_more_pages": true,
     *     "from": 1,
     *     "to": 10
     *   },
     *   "data": []
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "User pasien tidak ditemukan.",
     *   "data": []
     * }
     */
    public function index(Request $request)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status_booking' => 'nullable|in:Pending,DiPerjalanan,Tindakan,Selesai,Dibatalkan',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date',
            'sort_by' => 'nullable|in:created_at,tanggal_kunjungan,status_booking',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $user = $request->user();
        $pasien = $user?->pasien;

        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'User pasien tidak ditemukan.',
                'data' => []
            ], 404);
        }

        $perPage = $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])
            ->where('id_pasien', $pasien->id_pasien);

        if ($request->filled('status_booking')) {
            $query->where('status_booking', $request->input('status_booking'));
        }

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_kunjungan', '>=', $request->input('tanggal_dari'));
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_kunjungan', '<=', $request->input('tanggal_sampai'));
        }

        $query->orderBy($sortBy, $sortOrder);
        $bookings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar transaksi pasien',
            'pagination' => [
                'total' => $bookings->total(),
                'count' => $bookings->count(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
                'total_pages' => $bookings->lastPage(),
                'has_more_pages' => $bookings->hasMorePages(),
                'from' => $bookings->firstItem(),
                'to' => $bookings->lastItem(),
            ],
            'data' => BookingResource::collection($bookings->items()),
        ]);
    }

    /**
     * Detail transaksi pasien berdasarkan booking.
     *
     * @group Transaksi
     * @authenticated
     *
     * @urlParam id_booking integer required ID booking yang ingin dilihat. Example: 42
     */
    public function show($id_booking, Request $request)
    {
        $pasien = $request->user()?->pasien;

        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'User pasien tidak ditemukan.',
                'data' => [],
            ], 404);
        }

        $booking = Booking::with([
            'pasien',
            'layanan.kategori',
            'layanan.bhpItems',
            'layananItems.layanan.kategori',
            'layananItems.layanan.bhpItems',
            'tenagaMedis',
            'transaksi',
        ])
            ->where('id_booking', $id_booking)
            ->where('id_pasien', $pasien->id_pasien)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi booking tidak ditemukan.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi pasien berhasil diambil.',
            'data' => new TransaksiDetailResource($booking),
        ]);
    }

    /**
     * Buat Transaksi Baru
     *
     * Membuat transaksi baru dan mendapatkan token pembayaran Midtrans untuk proses checkout.
     *
     * @group Transaksi
     * @authenticated
     *
     * @bodyParam id_booking integer required ID booking yang akan dibayar. Example: 42
     * @bodyParam metode_pembayaran string required Metode pembayaran. Values: bank_transfer, gopay, shopeepay, qris, bca_klikbca. Example: bank_transfer
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Token pembayaran Midtrans",
     *   "token": "xxxx-xxxx-xxxx-xxxx-midtrans-snap-token",
     *   "redirect_url": "https://app.sandbox.midtrans.com/snap/v1/web/...",
     *   "data": {
     *     "id_transaksi": 42,
     *     "order_id": "BOOKING-42-20260902001",
     *     "jumlah_total": 160000,
     *     "jumlah_total_format": "Rp 160.000",
     *     "status_transaksi": "Pending",
     *     "metode_pembayaran": "bank_transfer"
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Booking tidak ditemukan.",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "id_booking": ["The id_booking field is required."],
     *     "metode_pembayaran": ["The metode_pembayaran field is required."]
     *   }
     * }
     */
    // public function store(Request $request)
    // {
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Token pembayaran Midtrans',
    //         'token' => 'dummy-midtrans-token'
    //     ]);
    // }

    /**
     * Konfirmasi Status Pembayaran
     *
     * Mengkonfirmasi dan memverifikasi status pembayaran dari Midtrans. Endpoint ini akan sync dengan Midtrans untuk mendapatkan status transaksi terbaru.
     *
     * @group Transaksi
     * @authenticated
     *
     * @bodyParam id_booking integer required ID booking yang akan dikonfirmasi. Example: 42
     * @bodyParam order_id string required Order ID dari Midtrans. Format: BOOKING-{id}-{timestamp}. Example: BOOKING-42-20260902001
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Status transaksi telah diperbarui.",
     *   "data": {
     *     "id_booking": 42,
     *     "booking_code": "B-260902-0000001",
     *     "transaction_status": "settlement",
     *     "booking_status": "Diproses",
     *     "status_transaksi": "Lunas",
     *     "waktu_bayar": "02 Sep 2026, 10:15 WIB",
     *     "jumlah_total": 160000,
     *     "jumlah_total_format": "Rp 160.000"
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Transaksi booking tidak ditemukan.",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "id_booking": ["The id_booking field is required."],
     *     "order_id": ["The order_id field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "success": false,
     *   "message": "Gagal memverifikasi status pembayaran.",
     *   "error": "Midtrans error message"
     * }
     */
    public function confirm(Request $request)
    {
        $validate = $request->validate([
            'id_booking' => 'required|exists:bookings,id_booking',
            'order_id' => 'required|string',
        ]);

        $booking = Booking::with(['transaksi', 'layananItems.layanan'])->findOrFail($validate['id_booking']);
        $transaction = $booking->transaksi;

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi booking tidak ditemukan.'
            ], 404);
        }

        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.is_sanitized');
        Config::$is3ds = config('services.midtrans.is_3ds');

        try {
            $status = Transaction::status($validate['order_id']);
            $transactionStatus = $status->transaction_status ?? null;

            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                $transaction->update([
                    'status_transaksi' => 'Lunas',
                    'waktu_bayar' => now(),
                ]);
                $booking->update(['status_booking' => 'Diproses']);
            } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                $transaction->update(['status_transaksi' => 'Gagal']);
                $booking->update(['status_booking' => 'Dibatalkan']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status transaksi telah diperbarui.',
                'data' => [
                    'transaction_status' => $transactionStatus,
                    'booking_status' => $booking->status_booking,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Midtrans confirm error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memverifikasi status pembayaran.',
            ], 500);
        }
    }

    /**
     * Midtrans Webhook Callback
     *
     * Endpoint webhook untuk menerima callback dari Midtrans setelah transaksi selesai. 
     * Endpoint ini PUBLIC (tanpa auth). Midtrans akan mengirim notifikasi pembayaran ke endpoint ini.
     *
     * @group Transaksi
     * @unauthenticated
     *
     * @bodyParam transaction_time string Waktu transaksi dari Midtrans. Example: 2026-09-02 10:15:30
     * @bodyParam transaction_status string Status transaksi. Values: settlement, capture, deny, cancel, expire. Example: settlement
     * @bodyParam order_id string Order ID dari Midtrans. Format: BOOKING-{id}-{timestamp}. Example: BOOKING-42-20260902001
     * @bodyParam gross_amount string Jumlah transaksi. Example: 160000.00
     * @bodyParam signature_key string Signature key untuk verifikasi. Example: xxxx-xxxx-xxxx
     * @bodyParam fraud_status string Status fraud detection. Example: accept
     * @bodyParam bank string Bank yang digunakan untuk transfer. Example: bca
     *
     * @response 200 {
     *   "status": "success"
     * }
     *
     * @response 400 {
     *   "status": "error",
     *   "message": "Order ID tidak ditemukan."
     * }
     *
     * @response 404 {
     *   "status": "error",
     *   "message": "Booking tidak ditemukan."
     * }
     */
    public function callback(Request $request)
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.is_sanitized');
        Config::$is3ds = config('services.midtrans.is_3ds');

        $notification = new Notification();
        $orderId = $notification->order_id ?? null;
        $transactionStatus = $notification->transaction_status ?? null;

        if (!$orderId) {
            return response()->json(['status' => 'error', 'message' => 'Order ID tidak ditemukan.'], 400);
        }

        $bookingId = null;
        if (preg_match('/^BOOKING\-(\d+)\-/', $orderId, $matches)) {
            $bookingId = (int) $matches[1];
        }

        if (!$bookingId) {
            return response()->json(['status' => 'error', 'message' => 'Booking ID tidak dapat diproses.'], 400);
        }

        $booking = Booking::with('transaksi')->find($bookingId);
        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $transaction = $booking->transaksi;

        if ($transaction) {
            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                $transaction->update([
                    'status_transaksi' => 'Lunas',
                    'waktu_bayar' => now(),
                ]);
                $booking->update(['status_booking' => 'Diproses']);
            } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                $transaction->update(['status_transaksi' => 'Gagal']);
                $booking->update(['status_booking' => 'Dibatalkan']);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
