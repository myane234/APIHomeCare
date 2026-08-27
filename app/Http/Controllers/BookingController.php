<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\MasterLayanan;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use App\Models\MasterTarif;
use App\Models\MasterKategoriTarif;
use App\Models\MasterTarifTransport;
use App\Models\MasterKomponenBiaya;
use App\Models\Pasien;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap as MidtransSnap;

class BookingController extends Controller
{
    /**
     * API Pasien: Menampilkan daftar booking milik pasien yang sedang login.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $pasien = $user?->pasien;

        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'User pasien tidak ditemukan.',
                'data' => []
            ], 404);
        }

        $query = Booking::with(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])
            ->where('id_pasien', $pasien->id_pasien);

        // Filter opsional
        if ($request->filled('status_booking')) {
            $query->where('status_booking', $request->input('status_booking'));
        }
        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_kunjungan', '>=', $request->input('tanggal_dari'));
        }
        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_kunjungan', '<=', $request->input('tanggal_sampai'));
        }

        $bookings = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar booking pasien',
            'total'   => $bookings->count(),
            'data'    => BookingResource::collection($bookings),
        ]);
    }

    /**
     * API Admin: Menampilkan SELURUH booking.
     * Filter: status_booking, tanggal_dari, tanggal_sampai, id_pasien, id_tenaga_medis
     */
    public function adminIndex(Request $request)
    {
        $query = Booking::with(['pasien', 'layanan', 'tenagaMedis', 'transaksi']);

        if ($request->filled('status_booking')) {
            $query->where('status_booking', $request->input('status_booking'));
        }
        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_kunjungan', '>=', $request->input('tanggal_dari'));
        }
        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_kunjungan', '<=', $request->input('tanggal_sampai'));
        }
        if ($request->filled('id_pasien')) {
            $query->where('id_pasien', $request->input('id_pasien'));
        }
        if ($request->filled('id_tenaga_medis')) {
            $query->where('id_tenaga_medis', $request->input('id_tenaga_medis'));
        }
        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->whereMonth('tanggal_kunjungan', $request->input('bulan'))
                  ->whereYear('tanggal_kunjungan', $request->input('tahun'));
        }

        $bookings = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success'   => true,
            'message'   => 'Daftar booking (admin)',
            'total'     => $bookings->count(),
            'data'      => BookingResource::collection($bookings),
        ]);
    }

    /**
     * Hitung jarak dua titik koordinat (dalam km) menggunakan Haversine Formula.
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) {
            return 0.0;
        }

        $earthRadius = 6371; // Jari-jari bumi dalam km

        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Mencari Tenaga Medis terdekat berdasarkan lokasi pasien & kategori layanan.
     */
    public function findNearestNakes($patientLat, $patientLng, $idLayanan = null, $idKategoriLayanan = null)
    {
        if (empty($patientLat) || empty($patientLng)) {
            return TenagaMedis::with(['user', 'pasien', 'kategoriLayanan'])
                ->where('status', 'approved')
                ->get()
                ->map(function ($nakes) {
                    $nakes->distance_km = 0.0;
                    return $nakes;
                });
        }

        $query = TenagaMedis::with(['user', 'pasien', 'kategoriLayanan', 'wilayahLayanan'])
            ->where('status', 'approved');

        if ($idLayanan) {
            $layanan = MasterLayanan::find($idLayanan);
            if ($layanan && $layanan->id_kategori_layanan) {
                $query->whereHas('kategoriLayanan', function ($q) use ($layanan) {
                    $q->where('kategori_layanans.id_kategori_layanan', $layanan->id_kategori_layanan);
                });
            }
        } elseif ($idKategoriLayanan) {
            $query->whereHas('kategoriLayanan', function ($q) use ($idKategoriLayanan) {
                $q->where('kategori_layanans.id_kategori_layanan', $idKategoriLayanan);
            });
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'])) {
            $nakesList = $query->select('*')
                ->selectRaw(
                    '(6371 * acos(greatest(-1.0, least(1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) AS distance_km',
                    [$patientLat, $patientLng, $patientLat]
                )
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('distance_km', 'asc')
                ->get();
        } else {
            $nakesList = $query->get()
                ->map(function ($nakes) use ($patientLat, $patientLng) {
                    if ($nakes->latitude !== null && $nakes->longitude !== null) {
                        $nakes->distance_km = $this->calculateDistance(
                            (float)$patientLat, (float)$patientLng,
                            (float)$nakes->latitude, (float)$nakes->longitude
                        );
                    } else {
                        $nakes->distance_km = 999999.0;
                    }
                    return $nakes;
                })
                ->sortBy('distance_km')
                ->values();
        }

        return $nakesList;
    }

    /**
     * API Pasien: Mendapatkan daftar Tenaga Medis terdekat dari lokasi yang dikirim.
     */
    public function getNearestNakesList(Request $request)
    {
        $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'latitude_kunjungan' => 'nullable|numeric',
            'longitude_kunjungan' => 'nullable|numeric',
            'id_layanan' => 'nullable',
            'id_kategori_layanan' => 'nullable',
        ]);

        $lat = $request->input('latitude') ?? $request->input('latitude_kunjungan');
        $lng = $request->input('longitude') ?? $request->input('longitude_kunjungan');

        $nakesList = $this->findNearestNakes($lat, $lng, $request->input('id_layanan'), $request->input('id_kategori_layanan'));

        return response()->json([
            'success' => true,
            'message' => 'Daftar Tenaga Medis terdekat',
            'data' => $nakesList
        ]);
    }

    /**
     * API Pasien: Membuat booking baru + Generate Snap Token Midtrans.
     */
    public function store(Request $request)
    {
        if ($request->has('payment_type')) {
            return $this->charge($request);
        }

        $validate = $request->validate([
            'id_layanan' => 'required',
            'id_kategori_tarif' => 'nullable|exists:master_kategori_tarif,id_kategori_tarif',
            'id_tenaga_medis' => 'nullable',
            'tanggal_kunjungan' => 'required|date',
            'jam_kunjungan' => 'required',
            'alamat_kunjungan' => 'nullable|string',
            'latitude_kunjungan' => 'required|numeric',
            'longitude_kunjungan' => 'required|numeric',
            'catatan' => 'nullable|string',
            'id_promo' => 'nullable',
            'id_kota' => 'nullable',
        ]);

        $user = $request->user();
        $pasien = $user?->pasien;

        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'User pasien tidak ditemukan.'
            ], 403);
        }

        $alamatKunjungan = $request->input('alamat_kunjungan') ?? $pasien->alamat_utama ?? null;
        if (empty($alamatKunjungan)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat kunjungan wajib diisi. Isi alamat pasien terlebih dahulu.'
            ], 422);
        }

        $idLayanan = is_array($validate['id_layanan']) ? ($validate['id_layanan'][0] ?? null) : $validate['id_layanan'];
        $layanan = MasterLayanan::with(['masterTarif', 'bhpItems'])->findOrFail($idLayanan);

        $patientLat = (float) $validate['latitude_kunjungan'];
        $patientLng = (float) $validate['longitude_kunjungan'];

        $tenagaMedisId = $validate['id_tenaga_medis'] ?? null;
        if (is_array($tenagaMedisId)) {
            $tenagaMedisId = $tenagaMedisId[0] ?? null;
        }

        $distance = 0.0;

        if ($tenagaMedisId) {
            $tenagaMedis = TenagaMedis::find($tenagaMedisId);
            if ($tenagaMedis && $tenagaMedis->latitude && $tenagaMedis->longitude) {
                $distance = $this->calculateDistance($patientLat, $patientLng, (float)$tenagaMedis->latitude, (float)$tenagaMedis->longitude);
            }
        } else {
            // Logic pencocokan Nakes terdekat secara otomatis
            $nearestList = $this->findNearestNakes($patientLat, $patientLng, $idLayanan);
            $nearestNakes = $nearestList->first();

            if ($nearestNakes) {
                $tenagaMedis = $nearestNakes;
                $tenagaMedisId = $nearestNakes->id_tenaga_medis;
                $distance = (float) ($nearestNakes->distance_km ?? 0.0);
                if ($distance > 1000) $distance = 0.0;
            } else {
                $tenagaMedis = TenagaMedis::where('status', 'approved')->first() ?? TenagaMedis::first();
                $tenagaMedisId = $tenagaMedis?->id_tenaga_medis;
                $distance = 0.0;
            }
        }

        if (!$tenagaMedisId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenaga medis belum tersedia untuk booking.'
            ], 422);
        }

        // 2. Load Master Tarif Blueprint (Layanan, Kota, Kategori Tarif)
        $idKota = $request->input('id_kota');
        $idKategoriTarif = $request->input('id_kategori_tarif');

        $masterTarifQuery = MasterTarif::with(['komponenTarif', 'kategoriTarif', 'layananTermasuk'])
            ->where('is_active', true);

        if ($idKategoriTarif) {
            $masterTarifQuery->where('id_kategori_tarif', $idKategoriTarif);
        }

        $masterTarif = (clone $masterTarifQuery)
            ->where('id_layanan', $layanan->id_layanan)
            ->when($idKota, fn($q) => $q->where('id_kota', $idKota))
            ->first();

        if (!$masterTarif) {
            $masterTarif = (clone $masterTarifQuery)
                ->where('id_layanan', $layanan->id_layanan)
                ->whereNull('id_kota')
                ->first();
        }

        if (!$masterTarif) {
            $masterTarif = MasterTarif::with(['komponenTarif', 'kategoriTarif'])
                ->where('id_layanan', $layanan->id_layanan)
                ->where('is_active', true)
                ->first();
        }

        // 3. Hitung SL (Tarif Layanan / Jasa Medis)
        $tarifLayananJasaMedis = (float) $layanan->harga;
        $kategoriTarifObj = $idKategoriTarif 
            ? MasterKategoriTarif::find($idKategoriTarif) 
            : ($masterTarif?->kategoriTarif ?? MasterKategoriTarif::where('is_default', true)->first());

        if ($kategoriTarifObj && (float)$kategoriTarifObj->biaya_tambahan > 0) {
            $tarifLayananJasaMedis += (float) $kategoriTarifObj->biaya_tambahan;
        }

        // 4. Hitung SB (Tarif BHP / Biaya Alat) & HPP BHP
        $tarifBahanHabisPakai = 0.0;
        $hppBhp = 0.0;
        foreach ($layanan->bhpItems as $bhpItem) {
            $qty = (int) ($bhpItem->pivot->qty_default ?? 1);
            $hargaJual = (float) $bhpItem->harga_jual;
            $hargaModal = (float) $bhpItem->harga_modal;
            $tarifBahanHabisPakai += $hargaJual * $qty;
            $hppBhp += $hargaModal * $qty;
        }

        // 5. Hitung ST (Tarif Transport)
        $tarifTransportasiFinal = 0.0;
        if (!$layanan->include_transport) {
            $transportMaster = null;
            if ($idKota) {
                $transportMaster = MasterTarifTransport::where('id_kota', $idKota)->first();
            }
            if ($transportMaster) {
                $tarifTransportasiFinal = (float)$transportMaster->tarif_awal + ($distance * (float)$transportMaster->tarif_per_kilometer);
            } else {
                $tarifTransportasiFinal = $distance > 0 ? (10000.0 + ($distance * 3000.0)) : 0.0;
            }
        }

        // 6. Hitung Komponen Tarif (Admin Fee & PPN / Pajak)
        $biayaAdministrasiAplikasi = 0.0;
        $persentasePpnPajak = 0.0;
        $nominalPpnPajak = 0.0;

        if ($masterTarif && $masterTarif->komponenTarif) {
            foreach ($masterTarif->komponenTarif as $komponen) {
                if (!$komponen->is_active) continue;

                if (in_array($komponen->tipe_komponen, ['admin_aplikasi', 'lainnya'])) {
                    if ($komponen->jenis_nilai === 'nominal') {
                        $biayaAdministrasiAplikasi += (float) $komponen->nilai;
                    } elseif ($komponen->jenis_nilai === 'persen') {
                        $biayaAdministrasiAplikasi += $tarifLayananJasaMedis * ((float)$komponen->nilai / 100);
                    }
                } elseif ($komponen->tipe_komponen === 'pajak') {
                    if ($komponen->jenis_nilai === 'persen') {
                        $persentasePpnPajak += (float) $komponen->nilai;
                    } elseif ($komponen->jenis_nilai === 'nominal') {
                        $nominalPpnPajak += (float) $komponen->nilai;
                    }
                }
            }
        }

        if ($persentasePpnPajak > 0 && $nominalPpnPajak == 0) {
            $nominalPpnPajak = ($tarifLayananJasaMedis + $tarifBahanHabisPakai + $tarifTransportasiFinal) * ($persentasePpnPajak / 100);
        }

        // 7. Hitung Fee Nakes (Hak Nakes) & Profit HC
        $feeType = $masterTarif?->fee_nakes_tipe ?? 'persen';
        $feeVal = (float) ($masterTarif?->fee_nakes_nilai ?? 80.0);

        if ($feeType === 'nominal') {
            $feeNakesNominalBase = (float) ($masterTarif?->fee_nakes_nominal > 0 ? $masterTarif->fee_nakes_nominal : $feeVal);
            $persentaseBagianNakes = $tarifLayananJasaMedis > 0 ? min(100, ($feeNakesNominalBase / $tarifLayananJasaMedis) * 100) : 80.0;
        } else {
            $persentaseBagianNakes = $feeVal;
            $feeNakesNominalBase = $tarifLayananJasaMedis * ($persentaseBagianNakes / 100);
        }

        $nominalHakNakes = $feeNakesNominalBase + $tarifTransportasiFinal;
        $totalTagihanPasien = $tarifLayananJasaMedis + $tarifBahanHabisPakai + $tarifTransportasiFinal + $biayaAdministrasiAplikasi + $nominalPpnPajak;

        $feeMidtrans = (float) env('FEE_MIDTRANS', 4000.0);
        $estimasiProfitHomeCare = ($tarifLayananJasaMedis - $feeNakesNominalBase) + ($tarifBahanHabisPakai - $hppBhp) + $biayaAdministrasiAplikasi - $feeMidtrans;

        $idPromo = $validate['id_promo'] ?? null;
        if (is_array($idPromo)) {
            $idPromo = $idPromo[0] ?? null;
        }

        DB::beginTransaction();

        try {
            $prefix = 'B-' . date('ymd');
            $todayCount = Booking::whereDate('created_at', now()->toDateString())->count();
            $nextSequence = $todayCount + 1; 
            $bookingCode = $prefix . str_pad($nextSequence, 7, '0', STR_PAD_LEFT);

            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'id_pasien' => $pasien->id_pasien,
                'id_layanan' => $layanan->id_layanan,
                'id_tenaga_medis' => $tenagaMedisId,
                'tanggal_kunjungan' => $validate['tanggal_kunjungan'],
                'jam_kunjungan' => $validate['jam_kunjungan'],
                'alamat_kunjungan' => $alamatKunjungan,
                'latitude_kunjungan' => $validate['latitude_kunjungan'],
                'longitude_kunjungan' => $validate['longitude_kunjungan'],
                'status_booking' => 'Pending',
            ]);

            $transaction = Transaksi::create([
                'id_booking' => $booking->id_booking,
                'jumlah_total' => $totalTagihanPasien,
                'metode_pembayaran' => 'QRIS',
                'status_transaksi' => 'Belum Bayar',
                'sl' => $tarifLayananJasaMedis,
                'sb' => $tarifBahanHabisPakai,
                'st' => $tarifTransportasiFinal,
                'ba' => $biayaAdministrasiAplikasi,
                'ppn' => $nominalPpnPajak,
                'persen_ppn' => $persentasePpnPajak,
                'persen_fee_nakes' => $persentaseBagianNakes,
                'fee_midtrans' => $feeMidtrans,
                'hpp_bhp' => $hppBhp,
                'hak_nakes' => $nominalHakNakes,
                'profit_hc' => $estimasiProfitHomeCare,
            ]);

            MidtransConfig::$serverKey = config('services.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-dummy-key');
            MidtransConfig::$isProduction = config('services.midtrans.is_production', false);
            MidtransConfig::$isSanitized = config('services.midtrans.is_sanitized', true);
            MidtransConfig::$is3ds = config('services.midtrans.is_3ds', true);

            $orderId = 'BOOKING-' . $booking->id_booking . '-' . time();

            $itemDetails = [];
            $itemDetails[] = [
                'id' => 'LYN-' . $layanan->id_layanan,
                'price' => (float) $tarifLayananJasaMedis,
                'quantity' => 1,
                'name' => substr($layanan->nama_layanan, 0, 50),
            ];

            if ($tarifBahanHabisPakai > 0) {
                $itemDetails[] = [
                    'id' => 'BHP-' . $booking->id_booking,
                    'price' => (float) $tarifBahanHabisPakai,
                    'quantity' => 1,
                    'name' => 'Bahan Habis Pakai (BHP)',
                ];
            }

            if ($tarifTransportasiFinal > 0) {
                $itemDetails[] = [
                    'id' => 'TRN-' . $booking->id_booking,
                    'price' => (float) $tarifTransportasiFinal,
                    'quantity' => 1,
                    'name' => 'Biaya Transportasi (' . round($distance, 1) . ' km)',
                ];
            }

            if ($biayaAdministrasiAplikasi > 0) {
                $itemDetails[] = [
                    'id' => 'ADM-' . $booking->id_booking,
                    'price' => (float) $biayaAdministrasiAplikasi,
                    'quantity' => 1,
                    'name' => 'Biaya Administrasi',
                ];
            }

            if ($nominalPpnPajak > 0) {
                $itemDetails[] = [
                    'id' => 'TAX-' . $booking->id_booking,
                    'price' => (float) $nominalPpnPajak,
                    'quantity' => 1,
                    'name' => 'PPN (' . $persentasePpnPajak . '%)',
                ];
            }

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (float) $totalTagihanPasien,
                ],
                'customer_details' => [
                    'first_name' => $pasien->nama_lengkap ?? $user->email ?? 'Pasien',
                    'email' => $user->email ?? 'no-reply@example.com',
                ],
                'item_details' => $itemDetails,
            ];

            if (app()->environment('testing')) {
                $snapToken = 'mock-snap-token-123';
                $redirectUrl = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mock';
            } else {
                $snap = MidtransSnap::createTransaction($params);
                $snapToken = $snap->token ?? null;
                $redirectUrl = $snap->redirect_url ?? null;
            }

            DB::commit();

            $booking->load(['pasien', 'layanan', 'tenagaMedis', 'transaksi']);

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dibuat. Silakan lanjutkan pembayaran.',
                'data' => [
                    'booking' => $booking,
                    'transaction' => $transaction,
                    'order_id' => $orderId,
                    'snap_token' => $snapToken,
                    'redirect_url' => $redirectUrl,
                    'distance_km' => round($distance, 2),
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat booking: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API Detail Booking berdasarkan ID
     */
    public function show($id)
    {
        $booking = Booking::with(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail booking',
            'data'    => new BookingResource($booking),
        ]);
    }

    /**
     * API Laporan / Ringkasan Transaksi satu Booking.
     * GET /booking/{id}/laporan
     */
    public function laporan($id)
    {
        $booking = Booking::with(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $t = $booking->transaksi;

        return response()->json([
            'success'   => true,
            'message'   => 'Laporan transaksi booking',
            'data'      => [
                // ── Identitas ────────────────────────────────────
                'booking_code'      => $booking->booking_code,
                'status_booking'    => $booking->status_booking,
                'status_label'      => (new BookingResource($booking))->resolve()['status_label'] ?? $booking->status_booking,

                // ── Waktu ────────────────────────────────────────
                'tanggal_kunjungan' => $booking->tanggal_kunjungan
                    ? Carbon::parse($booking->tanggal_kunjungan)->translatedFormat('l, d F Y')
                    : null,
                'jam_kunjungan'     => $booking->jam_kunjungan,
                'dibuat_pada'       => $booking->created_at
                    ? Carbon::parse($booking->created_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,

                // ── Pasien & Nakes ───────────────────────────────
                'pasien'            => [
                    'nama'          => $booking->pasien?->nama_lengkap,
                    'alamat'        => $booking->alamat_kunjungan,
                ],
                'tenaga_medis'      => [
                    'nama'          => $booking->tenagaMedis?->nama_lengkap,
                    'jenis'         => $booking->tenagaMedis?->jenis_tenaga_medis,
                ],
                'layanan'           => [
                    'nama'          => $booking->layanan?->nama_layanan,
                ],

                // ── Rincian Biaya ────────────────────────────────
                'rincian_biaya'     => $t ? [
                    ['label' => 'Tarif Layanan (SL)',      'nilai' => (float)$t->sl,  'format' => 'Rp ' . number_format((float)$t->sl, 0, ',', '.')],
                    ['label' => 'Bahan Habis Pakai (SB)',  'nilai' => (float)$t->sb,  'format' => 'Rp ' . number_format((float)$t->sb, 0, ',', '.')],
                    ['label' => 'Biaya Transportasi (ST)', 'nilai' => (float)$t->st,  'format' => 'Rp ' . number_format((float)$t->st, 0, ',', '.')],
                    ['label' => 'Biaya Admin Aplikasi',    'nilai' => (float)$t->ba,  'format' => 'Rp ' . number_format((float)$t->ba, 0, ',', '.')],
                    ['label' => 'PPN (' . (float)$t->persen_ppn . '%)', 'nilai' => (float)$t->ppn, 'format' => 'Rp ' . number_format((float)$t->ppn, 0, ',', '.')],
                ] : [],

                // ── Total & Pembayaran ───────────────────────────
                'jumlah_total'          => $t ? (float)$t->jumlah_total : 0,
                'jumlah_total_format'   => $t ? 'Rp ' . number_format((float)$t->jumlah_total, 0, ',', '.') : '-',
                'metode_pembayaran'     => $t?->metode_pembayaran,
                'status_transaksi'      => $t?->status_transaksi,
                'waktu_bayar'           => $t?->waktu_bayar
                    ? Carbon::parse($t->waktu_bayar)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,

                // ── Bagi Hasil (internal) ────────────────────────
                'bagi_hasil'            => $t ? [
                    'hak_nakes'         => (float)$t->hak_nakes,
                    'hak_nakes_format'  => 'Rp ' . number_format((float)$t->hak_nakes, 0, ',', '.'),
                    'profit_hc'         => (float)$t->profit_hc,
                    'profit_hc_format'  => 'Rp ' . number_format((float)$t->profit_hc, 0, ',', '.'),
                    'fee_midtrans'      => (float)$t->fee_midtrans,
                    'hpp_bhp'           => (float)$t->hpp_bhp,
                ] : null,
            ],
        ]);
    }

    /**
     * API Update Status Booking oleh Admin/Nakes
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status_booking' => 'required|string|in:Pending,DiPerjalanan,Tindakan,Selesai,Dibatalkan',
        ]);

        $booking = Booking::with(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        $booking->status_booking = $request->input('status_booking');
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Status booking berhasil diperbarui menjadi ' . $booking->status_booking,
            'data'    => new BookingResource($booking->load(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])),
        ]);
    }

    /**
     * API Direct Midtrans Charge
     */
    public function charge(Request $request)
    {
        $request->validate([
            'payment_type' => 'required|string',
            'transaction_details' => 'required|array',
            'transaction_details.order_id' => 'required|string',
            'transaction_details.gross_amount' => 'required|numeric',
        ]);

        $serverKey = config('services.midtrans.server_key');
        $isProduction = config('services.midtrans.is_production');
        $url = $isProduction 
            ? 'https://api.midtrans.com/v2/charge' 
            : 'https://api.sandbox.midtrans.com/v2/charge';

        try {
            $client = Http::withBasicAuth($serverKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            if (config('app.env') === 'local') {
                $client->withoutVerifying();
            }

            $response = $client->post($url, $request->all());

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal meneruskan pembayaran ke Midtrans: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Cek Status Transaksi & Booking
     */
    public function checkStatus($idTransaksi)
    {
        $transaksi = Transaksi::with(['booking.pasien', 'booking.layanan', 'booking.tenagaMedis'])->find($idTransaksi);

        if (!$transaksi) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.'
            ], 404);
        }

        $booking = $transaksi->booking;

        return response()->json([
            'success' => true,
            'message' => 'Status transaksi',
            'data'    => [
                'id_transaksi'      => $transaksi->id_transaksi,
                'id_booking'        => $transaksi->id_booking,
                'booking_code'      => $booking?->booking_code,
                'status_booking'    => $booking?->status_booking,
                'status_transaksi'  => $transaksi->status_transaksi,
                'metode_pembayaran' => $transaksi->metode_pembayaran,
                'jumlah_total'      => (float) $transaksi->jumlah_total,
                'jumlah_total_format'=> 'Rp ' . number_format((float)$transaksi->jumlah_total, 0, ',', '.'),
                'waktu_bayar'       => $transaksi->waktu_bayar
                    ? Carbon::parse($transaksi->waktu_bayar)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,
                'dibuat_pada'       => $booking?->created_at
                    ? Carbon::parse($booking->created_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,
                'booking_detail'    => $booking ? new BookingResource($booking) : null,
            ],
        ]);
    }

    /**
     * API Nakes: Menampilkan daftar booking yang ditugaskan ke Nakes login atau booking pending terdekat.
     */
    public function nakesIndex(Request $request)
    {
        $user = $request->user();
        $nakes = TenagaMedis::where('id_user', $user?->id_user)->first();

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.',
                'data' => []
            ], 404);
        }

        $bookings = Booking::with(['pasien', 'layanan', 'transaksi'])
            ->where(function ($q) use ($nakes) {
                $q->where('id_tenaga_medis', $nakes->id_tenaga_medis)
                  ->orWhereNull('id_tenaga_medis');
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar order / booking untuk Tenaga Medis',
            'total'   => $bookings->count(),
            'data'    => BookingResource::collection($bookings),
        ]);
    }

    /**
     * API Nakes: Terima Order Booking (Basic flow).
     */
    public function nakesAcceptBooking(Request $request, $id)
    {
        $user = $request->user();
        $nakes = TenagaMedis::where('id_user', $user?->id_user)->first();

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang dapat menerima order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'transaksi'])->find($id);

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

        if ($booking->id_tenaga_medis && (int)$booking->id_tenaga_medis !== (int)$nakes->id_tenaga_medis && $booking->status_booking !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order ini telah diambil oleh tenaga medis lain.'
            ], 400);
        }

        $booking->id_tenaga_medis = $nakes->id_tenaga_medis;
        $booking->status_booking = 'DiPerjalanan';
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil diterima. Status booking diperbarui menjadi DiPerjalanan.',
            'data'    => new BookingResource($booking->load(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])),
        ]);
    }

    /**
     * API Nakes: Update Status Order (DiPerjalanan, Tindakan, Selesai, Dibatalkan).
     */
    public function nakesUpdateStatus(Request $request, $id)
    {
        $request->validate([
            'status_booking' => 'required|string|in:Pending,DiPerjalanan,Tindakan,Selesai,Dibatalkan',
        ]);

        $user = $request->user();
        $nakes = TenagaMedis::where('id_user', $user?->id_user)->first();

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang dapat memperbarui status order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ((int)$booking->id_tenaga_medis !== (int)$nakes->id_tenaga_medis) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengubah status booking ini.'
            ], 403);
        }

        $booking->status_booking = $request->input('status_booking');
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Status order berhasil diperbarui menjadi ' . $booking->status_booking,
            'data'    => new BookingResource($booking->load(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])),
        ]);
    }
}