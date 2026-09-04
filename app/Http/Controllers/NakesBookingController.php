<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingBhp;
use App\Models\BhpItem;
use App\Models\MasterLayanan;
use App\Models\MasterTarifTransport;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller terpisah khusus untuk Workflow Booking & Tindakan Tenaga Medis (Nakes).
 */
class NakesBookingController extends Controller
{
    /**
     * Helper untuk mendapatkan profil Nakes yang sedang login.
     */
    private function getLoggedNakes(Request $request): ?TenagaMedis
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user instanceof TenagaMedis) {
            return $user;
        }

        if (isset($user->id_tenaga_medis)) {
            return TenagaMedis::find($user->id_tenaga_medis);
        }

        return TenagaMedis::where('id_user', $user->id_user ?? $user->id)->first();
    }

    /**
     * Hitung jarak dua titik koordinat (dalam km) menggunakan Haversine Formula.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) {
            return 0.0;
        }

        $earthRadius = 6371;
        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLon = deg2rad((float) $lon2 - (float) $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    private function resolveCoordinates($latitude, $longitude, ?string $address): ?array
    {
        if ($latitude !== null && $longitude !== null && (float) $latitude !== 0.0 && (float) $longitude !== 0.0) {
            return [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }
        return null;
    }

    private function isPaidTransaction(?Transaksi $transaksi): bool
    {
        if (!$transaksi) {
            return false;
        }

        $status = strtolower((string) $transaksi->status_transaksi);
        return in_array($status, ['lunas', 'sudah bayar', 'settlement', 'success'], true);
    }

    private function hasRejectedOrder(Booking $booking, TenagaMedis $nakes): bool
    {
        $rejections = is_array($booking->catatan_penolakan) ? $booking->catatan_penolakan : [];
        foreach ($rejections as $item) {
            if (isset($item['id_tenaga_medis']) && (int) $item['id_tenaga_medis'] === (int) $nakes->id_tenaga_medis) {
                return true;
            }
        }
        return false;
    }

    /**
     * API Nakes: Daftar booking milik Nakes yang login.
     */
    public function index(Request $request)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.',
                'data' => []
            ], 404);
        }

        $bookings = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem'])
            ->where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar booking Tenaga Medis',
            'total' => $bookings->count(),
            'data' => BookingResource::collection($bookings),
        ]);
    }

    /**
     * API Nakes: Antrean Order Masuk yang tersedia untuk Nakes.
     */
    public function ordersQueue(Request $request)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.',
                'data' => []
            ], 404);
        }

        $bookings = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'transaksi'])
            ->where(function ($q) use ($nakes) {
                $q->where('id_tenaga_medis', $nakes->id_tenaga_medis)
                    ->orWhereNull('id_tenaga_medis');
            })
            ->whereHas('transaksi', function ($query) {
                $query->whereRaw("LOWER(status_transaksi) IN ('lunas', 'sudah bayar', 'settlement', 'success')");
            })
            ->orderByDesc('created_at')
            ->get();

        $bookings = $bookings
            ->reject(fn(Booking $booking) => $this->hasRejectedOrder($booking, $nakes))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Daftar order / booking untuk Tenaga Medis',
            'total' => $bookings->count(),
            'data' => BookingResource::collection($bookings),
        ]);
    }

    /**
     * API Nakes: Detail Order / Booking.
     */
    public function show(Request $request, $id)
    {
        $booking = Booking::with(['pasien', 'layanan.kategori', 'layananItems.layanan.kategori', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail booking untuk Nakes',
            'data' => new BookingResource($booking),
        ]);
    }

    /**
     * API Nakes: Terima Order Booking -> Status DiPerjalanan.
     */
    public function acceptBooking(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang dapat menerima order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if (in_array($booking->status_booking, ['Selesai', 'Dibatalkan'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order ini sudah ' . strtolower($booking->status_booking) . ' dan tidak dapat diterima.'
            ], 400);
        }

        if ($this->hasRejectedOrder($booking, $nakes)) {
            return response()->json([
                'success' => false,
                'message' => 'Order ini sudah Anda tolak dan tidak dapat diterima kembali.',
            ], 422);
        }

        if (!$this->isPaidTransaction($booking->transaksi)) {
            return response()->json([
                'success' => false,
                'message' => 'Order belum dapat diterima karena pembayaran belum selesai.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $updated = Booking::where('id_booking', $id)
                ->where(function ($q) use ($nakes) {
                    $q->whereNull('id_tenaga_medis')
                        ->orWhere('id_tenaga_medis', $nakes->id_tenaga_medis);
                })
                ->where('status_booking', 'Pending')
                ->update([
                    'id_tenaga_medis' => $nakes->id_tenaga_medis,
                    'status_booking' => 'DiPerjalanan',
                ]);

            if (!$updated) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order ini telah diambil oleh tenaga medis lain atau sudah tidak menunggu.'
                ], 400);
            }

            app(WebSocketController::class)->ensureChatRoom($booking->load(['pasien', 'tenagaMedis']));

            // Recalculate Transport Nakes
            $transaksi = $booking->transaksi;
            $nakesCoordinates = $this->resolveCoordinates($nakes->latitude, $nakes->longitude, $nakes->alamat_lengkap);
            $bookingCoordinates = $this->resolveCoordinates($booking->latitude_kunjungan, $booking->longitude_kunjungan, $booking->alamat_kunjungan);

            if ($transaksi && $nakesCoordinates && $bookingCoordinates) {
                $actualDistance = $this->calculateDistance(
                    $nakesCoordinates['latitude'],
                    $nakesCoordinates['longitude'],
                    $bookingCoordinates['latitude'],
                    $bookingCoordinates['longitude']
                );

                $actualTransportCost = 0.0;
                $layananItems = $booking->layananItems;
                $layananList = $layananItems->isNotEmpty()
                    ? $layananItems->map(fn($item) => $item->layanan)->filter()
                    : collect([$booking->layanan])->filter();

                if ($layananList->contains(fn($layanan) => !$layanan->include_transport)) {
                    $actualTransportCost = $actualDistance > 0 ? (10000.0 + ($actualDistance * 3000.0)) : 0.0;
                }
                $actualTransportCost = (int) round($actualTransportCost);
                $originalSt = (float) $transaksi->st;
                $biayaTambahan = max(0, $actualTransportCost - $originalSt);

                $feeNakesBase = (float) $transaksi->hak_nakes - $originalSt;
                $newHakNakes = $feeNakesBase + $actualTransportCost;
                $newProfitHc = (float) $transaksi->profit_hc - $biayaTambahan;

                $transaksi->update([
                    'hak_nakes' => $newHakNakes,
                    'profit_hc' => $newProfitHc,
                ]);
            }

            DB::commit();

            $booking->refresh()->load(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi']);

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil diterima. Status booking diperbarui menjadi DiPerjalanan.',
                'data' => new BookingResource($booking),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerima order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Nakes: Tolak Order Booking.
     */
    public function rejectBooking(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang dapat menolak order.'
            ], 403);
        }

        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        $rejections = is_array($booking->catatan_penolakan) ? $booking->catatan_penolakan : [];
        $rejections[] = [
            'id_tenaga_medis' => $nakes->id_tenaga_medis,
            'nama_tenaga_medis' => $nakes->nama_lengkap,
            'alasan' => $request->input('alasan', 'Menolak order'),
            'waktu' => now()->toIso8601String(),
        ];

        $booking->catatan_penolakan = $rejections;
        if ((int) $booking->id_tenaga_medis === (int) $nakes->id_tenaga_medis) {
            $booking->id_tenaga_medis = null;
        }
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil ditolak.',
        ]);
    }

    /**
     * API Nakes: Ubah Status Booking Menjadi Tindakan.
     */
    public function startTindakan(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ($booking->id_tenaga_medis && (int)$booking->id_tenaga_medis !== (int)$nakes->id_tenaga_medis) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak memperbarui status booking ini.'
            ], 403);
        }

        if ($booking->status_booking === 'Selesai' || $booking->status_booking === 'Dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'Status booking sudah ' . $booking->status_booking . ' dan tidak dapat diubah lagi.'
            ], 400);
        }

        $booking->status_booking = 'Tindakan';
        $booking->save();

        // Inisialisasi awal record booking_bhp dari mapping_layanan_bhp jika belum ada
        $this->ensureBookingBhpInitialized($booking);

        $booking->refresh()->load(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem']);

        return response()->json([
            'success' => true,
            'message' => 'Status booking berhasil diperbarui menjadi Tindakan.',
            'data' => new BookingResource($booking),
        ]);
    }

    /**
     * Private helper: Inisialisasi booking_bhp berdasarkan daftar BHP layanan dalam booking.
     */
    private function ensureBookingBhpInitialized(Booking $booking): void
    {
        $layananIds = $booking->layananItems->isNotEmpty()
            ? $booking->layananItems->pluck('id_layanan')->filter()->unique()->all()
            : array_filter([$booking->id_layanan]);

        if (empty($layananIds)) {
            return;
        }

        $layanans = MasterLayanan::with('bhpItems')->whereIn('id_layanan', $layananIds)->get();

        foreach ($layanans as $layanan) {
            foreach ($layanan->bhpItems as $bhp) {
                $existing = BookingBhp::where('id_booking', $booking->id_booking)
                    ->where('id_layanan', $layanan->id_layanan)
                    ->where('id_bhp', $bhp->id_bhp)
                    ->first();

                if (!$existing) {
                    $qtyDefault = (int) ($bhp->pivot->qty_default ?? 1);
                    BookingBhp::create([
                        'id_booking' => $booking->id_booking,
                        'id_layanan' => $layanan->id_layanan,
                        'id_bhp' => $bhp->id_bhp,
                        'qty_default' => $qtyDefault,
                        'qty_real' => $qtyDefault,
                        'qty_tambahan' => 0,
                        'harga_jual' => (float) $bhp->harga_jual,
                        'harga_modal' => (float) $bhp->harga_modal,
                        'total_sb_tambahan' => 0,
                        'total_hpp_tambahan' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * API Nakes: Ambil Daftar BHP yang dapat/sudah digunakan pada booking ini.
     */
    public function getBhpList(Request $request, $id)
    {
        $booking = Booking::with(['layananItems.layanan.bhpItems', 'layanan.bhpItems', 'bookingBhp.bhpItem'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        // Pastikan record booking_bhp sudah terinisialisasi
        $this->ensureBookingBhpInitialized($booking);

        $bookingBhps = BookingBhp::with(['bhpItem', 'layanan'])
            ->where('id_booking', $booking->id_booking)
            ->get();

        $data = $bookingBhps->map(function ($item) {
            return [
                'id_booking_bhp' => $item->id_booking_bhp,
                'id_layanan' => $item->id_layanan,
                'nama_layanan' => $item->layanan?->nama_layanan,
                'id_bhp' => $item->id_bhp,
                'nama_bhp' => $item->bhpItem?->nama_bhp,
                'harga_jual' => (float) $item->harga_jual,
                'harga_modal' => (float) $item->harga_modal,
                'qty_default' => (int) $item->qty_default,
                'qty_real' => (int) $item->qty_real,
                'qty_tambahan' => (int) $item->qty_tambahan,
                'total_sb_tambahan' => (float) $item->total_sb_tambahan,
                'total_hpp_tambahan' => (float) $item->total_hpp_tambahan,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar data BHP untuk booking',
            'data' => $data,
        ]);
    }

    /**
     * API Nakes: Perbarui Kuantitas BHP saat/sebelum Tindakan Selesai.
     * Nakes hanya bisa menambah quantity (qty_real >= qty_default).
     */
    public function updateBhp(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.'
            ], 403);
        }

        $booking = Booking::with('transaksi')->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ($booking->status_booking === 'Selesai' || $booking->status_booking === 'Dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'BHP tidak dapat diperbarui karena booking sudah ' . $booking->status_booking
            ], 400);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.id_bhp' => 'required|integer',
            'items.*.qty_real' => 'required|integer|min:0',
        ]);

        $this->ensureBookingBhpInitialized($booking);

        DB::beginTransaction();
        try {
            $totalSbTambahanBooking = 0.0;
            $totalHppTambahanBooking = 0.0;

            foreach ($request->input('items') as $inputItem) {
                $idBhp = $inputItem['id_bhp'];
                $qtyRealInput = (int) $inputItem['qty_real'];

                $bookingBhp = BookingBhp::where('id_booking', $booking->id_booking)
                    ->where('id_bhp', $idBhp)
                    ->first();

                if ($bookingBhp) {
                    $qtyDefault = (int) $bookingBhp->qty_default;
                    // Pastikan Nakes tidak mengurangi di bawah qty_default
                    $qtyReal = max($qtyDefault, $qtyRealInput);
                    $qtyTambahan = $qtyReal - $qtyDefault;

                    $totalSbTambahan = $qtyTambahan * (float) $bookingBhp->harga_jual;
                    $totalHppTambahan = $qtyTambahan * (float) $bookingBhp->harga_modal;

                    $bookingBhp->update([
                        'qty_real' => $qtyReal,
                        'qty_tambahan' => $qtyTambahan,
                        'total_sb_tambahan' => $totalSbTambahan,
                        'total_hpp_tambahan' => $totalHppTambahan,
                    ]);
                }
            }

            // Hitung total SB Tambahan & HPP Tambahan untuk booking ini
            $allBhps = BookingBhp::where('id_booking', $booking->id_booking)->get();
            $totalSbTambahanBooking = (float) $allBhps->sum('total_sb_tambahan');
            $totalHppTambahanBooking = (float) $allBhps->sum('total_hpp_tambahan');

            // Update snapshot di transaksi jika ada
            if ($booking->transaksi) {
                $t = $booking->transaksi;
                $newSbTambahan = $totalSbTambahanBooking;
                $newHppTambahan = $totalHppTambahanBooking;

                $sl = (float) $t->sl;
                $sb = (float) $t->sb;
                $st = (float) $t->st;
                $ba = (float) $t->ba;
                $ppn = (float) $t->ppn;
                $feeMidtrans = (float) $t->fee_midtrans;
                $hakNakes = (float) $t->hak_nakes;
                $hppBhpBase = (float) $t->hpp_bhp;
                $feeNakesBase = $hakNakes - $st;

                $jumlahTotalBaru = $sl + $sb + $newSbTambahan + $st + $ba + $ppn;
                $profitHcBaru = ($sl - $feeNakesBase) + (($sb + $newSbTambahan) - ($hppBhpBase + $newHppTambahan)) + $ba - $feeMidtrans;

                $t->update([
                    'sb_tambahan' => $newSbTambahan,
                    'hpp_bhp_tambahan' => $newHppTambahan,
                    'jumlah_total' => $jumlahTotalBaru,
                    'profit_hc' => $profitHcBaru,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data kuantitas BHP berhasil diperbarui.',
                'data' => [
                    'sb_tambahan' => $totalSbTambahanBooking,
                    'hpp_bhp_tambahan' => $totalHppTambahanBooking,
                    'bhp_items' => BookingBhp::with(['bhpItem', 'layanan'])->where('id_booking', $booking->id_booking)->get(),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui BHP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Nakes: Selesaikan Booking setelah tindakan dan penambahan BHP.
     */
    public function completeBooking(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ($booking->id_tenaga_medis && (int)$booking->id_tenaga_medis !== (int)$nakes->id_tenaga_medis) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak menyelesaikan booking ini.'
            ], 403);
        }

        if ($booking->status_booking === 'Selesai') {
            return response()->json([
                'success' => true,
                'message' => 'Booking sudah berstatus Selesai.',
                'data' => new BookingResource($booking),
            ]);
        }

        if ($booking->status_booking === 'Dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'Booking yang dibatalkan tidak dapat diselesaikan.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Hitung total SB Tambahan & HPP Tambahan dari booking_bhp
            $allBhps = BookingBhp::where('id_booking', $booking->id_booking)->get();
            $totalSbTambahanBooking = (float) $allBhps->sum('total_sb_tambahan');
            $totalHppTambahanBooking = (float) $allBhps->sum('total_hpp_tambahan');

            if ($booking->transaksi) {
                $t = $booking->transaksi;
                $sl = (float) $t->sl;
                $sb = (float) $t->sb;
                $st = (float) $t->st;
                $ba = (float) $t->ba;
                $ppn = (float) $t->ppn;
                $feeMidtrans = (float) $t->fee_midtrans;
                $hakNakes = (float) $t->hak_nakes;
                $hppBhpBase = (float) $t->hpp_bhp;
                $feeNakesBase = $hakNakes - $st;

                $jumlahTotalBaru = $sl + $sb + $totalSbTambahanBooking + $st + $ba + $ppn;
                $profitHcBaru = ($sl - $feeNakesBase) + (($sb + $totalSbTambahanBooking) - ($hppBhpBase + $totalHppTambahanBooking)) + $ba - $feeMidtrans;

                $t->update([
                    'sb_tambahan' => $totalSbTambahanBooking,
                    'hpp_bhp_tambahan' => $totalHppTambahanBooking,
                    'jumlah_total' => $jumlahTotalBaru,
                    'profit_hc' => $profitHcBaru,
                ]);
            }

            $booking->status_booking = 'Selesai';
            $booking->save();

            DB::commit();

            $booking->refresh()->load(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem']);

            return response()->json([
                'success' => true,
                'message' => 'Tindakan selesai. Booking berhasil diselesaikan.',
                'data' => new BookingResource($booking),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyelesaikan booking: ' . $e->getMessage()
            ], 500);
        }
    }
}
