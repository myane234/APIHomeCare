<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingLayanan;
use App\Models\MasterLayanan;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use App\Http\Controllers\WebSocketController;
use App\Models\MasterTarif;
use App\Models\MasterKategoriTarif;
use App\Models\MasterTarifTransport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * @group Booking Management
 */
class BookingController extends Controller
{
    /**
     * API Pasien: Menampilkan daftar booking milik pasien yang sedang login dengan pagination.
     * 
     * Query Parameters:
     * - page: Halaman (default: 1)
     * - per_page: Jumlah data per halaman (default: 10)
     * - status_booking: Filter by status (Pending, DiPerjalanan, Tindakan, Selesai, Dibatalkan)
     * - tanggal_dari: Filter dari tanggal (format: Y-m-d)
     * - tanggal_sampai: Filter sampai tanggal (format: Y-m-d)
     * - sort_by: Urutkan by field (created_at, tanggal_kunjungan, status_booking) default: created_at
     * - sort_order: Arah urutan (asc/desc) default: desc
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

        // Filter by status
        if ($request->filled('status_booking')) {
            $query->where('status_booking', $request->input('status_booking'));
        }

        // Filter by tanggal kunjungan - dari
        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_kunjungan', '>=', $request->input('tanggal_dari'));
        }

        // Filter by tanggal kunjungan - sampai
        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_kunjungan', '<=', $request->input('tanggal_sampai'));
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated data
        $bookings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar booking pasien',
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
     * API Pasien: Menampilkan booking terkini/aktif beserta lokasi realtime nakes & estimasi perjalanan.
     * GET /api/booking/terkini atau /api/pasien/booking-aktif
     */
    public function pasienActiveTracking(Request $request)
    {
        $user = $request->user();
        $pasien = $user?->pasien;

        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'User pasien tidak ditemukan.',
                'data' => null
            ], 404);
        }


        $activeBooking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem'])
            ->where('id_pasien', $pasien->id_pasien)
            ->whereIn('status_booking', ['Pending', 'DiPerjalanan', 'Tindakan'])
            ->orderByDesc('created_at')
            ->first();


        if (!$activeBooking) {
            $activeBooking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem'])
                ->where('id_pasien', $pasien->id_pasien)
                ->orderByDesc('created_at')
                ->first();
        }

        if (!$activeBooking) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada transaksi / booking pasien.',
                'data' => null,
            ]);
        }

        $nakes = $activeBooking->tenagaMedis;
        $trackingData = null;

        if ($nakes && $nakes->latitude && $nakes->longitude && $activeBooking->latitude_kunjungan && $activeBooking->longitude_kunjungan) {
            $distanceKm = $this->calculateDistance(
                (float) $nakes->latitude,
                (float) $nakes->longitude,
                (float) $activeBooking->latitude_kunjungan,
                (float) $activeBooking->longitude_kunjungan
            );


            $estimasiMenit = max(5, (int) round(($distanceKm / 25) * 60));

            $trackingData = [
                'jarak_km' => $distanceKm,
                'estimasi_menit_sampai' => $estimasiMenit,
                'lokasi_nakes' => [
                    'latitude' => (float) $nakes->latitude,
                    'longitude' => (float) $nakes->longitude,
                    'alamat' => $nakes->alamat_lengkap,
                ],
                'lokasi_kunjungan' => [
                    'latitude' => (float) $activeBooking->latitude_kunjungan,
                    'longitude' => (float) $activeBooking->longitude_kunjungan,
                    'alamat' => $activeBooking->alamat_kunjungan,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data booking & tracking nakes terkini',
            'data' => [
                'booking' => new BookingResource($activeBooking),
                'tenaga_medis_tracking' => $nakes ? [
                    'id_tenaga_medis' => $nakes->id_tenaga_medis,
                    'nama_lengkap' => $nakes->nama_lengkap,
                    'nama_panggilan' => $nakes->nama_panggilan,
                    'jenis_tenaga_medis' => $nakes->jenis_tenaga_medis,
                    'foto_profile' => $nakes->foto_profile,
                    'no_telp' => $nakes->no_telp,
                    'latitude' => $nakes->latitude ? (float) $nakes->latitude : null,
                    'longitude' => $nakes->longitude ? (float) $nakes->longitude : null,
                ] : null,
                'tracking_info' => $trackingData,
            ],
        ]);
    }

    /**
     * API Admin: Menampilkan SELURUH booking dengan pagination.
     * 
     * Query Parameters:
     * - page: Halaman (default: 1)
     * - per_page: Jumlah data per halaman (default: 15)
     * - status_booking: Filter by status
     * - tanggal_dari: Filter dari tanggal (format: Y-m-d)
     * - tanggal_sampai: Filter sampai tanggal (format: Y-m-d)
     * - id_pasien: Filter by pasien ID
     * - id_tenaga_medis: Filter by nakes ID
     * - bulan: Filter by bulan (1-12) - harus pairing dengan tahun
     * - tahun: Filter by tahun
     * - sort_by: Urutkan by field (created_at, tanggal_kunjungan, status_booking) default: created_at
     * - sort_order: Arah urutan (asc/desc) default: desc
     */
    public function adminIndex(Request $request)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status_booking' => 'nullable|in:Pending,DiPerjalanan,Tindakan,Selesai,Dibatalkan',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date',
            'id_pasien' => 'nullable|integer',
            'id_tenaga_medis' => 'nullable|integer',
            'id_layanan' => 'nullable|integer',
            'bulan' => 'nullable|integer|min:1|max:12',
            'tahun' => 'nullable|integer|min:2000|max:2100',
            'sort_by' => 'nullable|in:created_at,tanggal_kunjungan,status_booking',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi', 'bookingBhp.bhpItem']);

        // Filter by status
        if ($request->filled('status_booking')) {
            $query->where('status_booking', $request->input('status_booking'));
        }


        if ($request->filled('id_layanan')) {
            $idLayananFilter = $request->input('id_layanan');
            $query->where(function ($q) use ($idLayananFilter) {
                $q->where('id_layanan', $idLayananFilter)
                    ->orWhereHas('layananItems', function ($q2) use ($idLayananFilter) {
                        $q2->where('id_layanan', $idLayananFilter);
                    });
            });
        }


        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_kunjungan', '>=', $request->input('tanggal_dari'));
        }

        // Filter by tanggal kunjungan - sampai
        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_kunjungan', '<=', $request->input('tanggal_sampai'));
        }

        // Filter by pasien
        if ($request->filled('id_pasien')) {
            $query->where('id_pasien', $request->input('id_pasien'));
        }

        // Filter by nakes
        if ($request->filled('id_tenaga_medis')) {
            $query->where('id_tenaga_medis', $request->input('id_tenaga_medis'));
        }

        // Filter by bulan & tahun
        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->whereMonth('tanggal_kunjungan', $request->input('bulan'))
                ->whereYear('tanggal_kunjungan', $request->input('tahun'));
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated data
        $bookings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar booking (admin)',
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
     * Hitung jarak dua titik koordinat (dalam km) menggunakan Haversine Formula.
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2): float
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

    /**
     * Mengambil koordinat dari input atau geocoding alamat tanpa menyimpan hasilnya.
     */
    private function resolveCoordinates($latitude, $longitude, ?string $address): ?array
    {
        if ($latitude !== null && $longitude !== null && (float) $latitude !== 0.0 && (float) $longitude !== 0.0) {
            return [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        if (!is_string($address) || trim($address) === '') {
            return null;
        }

        $key = config('services.locationiq.key');
        if (!$key) {
            Log::error('LocationIQ key is not configured.');
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->retry(2, 250)
                ->timeout(10)
                ->get('https://us1.locationiq.com/v1/search', [
                    'key' => $key,
                    'q' => trim($address),
                    'format' => 'json',
                    'countrycodes' => 'id',
                    'limit' => 1,
                ]);

            $result = $response->successful() ? ($response->json()[0] ?? null) : null;
            if ($result && isset($result['lat'], $result['lon'])) {
                return [
                    'latitude' => (float) $result['lat'],
                    'longitude' => (float) $result['lon'],
                ];
            }
        } catch (\Throwable $e) {
            Log::error('LocationIQ geocoding failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Isi hanya koordinat yang kosong dari hasil geocoding.
     */
    private function storeMissingCoordinates(
        $model,
        ?array $coordinates,
        string $latitudeColumn = 'latitude',
        string $longitudeColumn = 'longitude'
    ): void {
        if (!$coordinates) {
            return;
        }

        $changed = false;

        if ($model->{$latitudeColumn} === null || (float) $model->{$latitudeColumn} === 0.0) {
            $model->{$latitudeColumn} = $coordinates['latitude'];
            $changed = true;
        }

        if ($model->{$longitudeColumn} === null || (float) $model->{$longitudeColumn} === 0.0) {
            $model->{$longitudeColumn} = $coordinates['longitude'];
            $changed = true;
        }

        if ($changed) {
            $model->save();
        }
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
            ->where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0);

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
                ->orderBy('distance_km', 'asc')
                ->get();
        } else {
            $nakesList = $query->get()
                ->map(function ($nakes) use ($patientLat, $patientLng) {
                    $nakes->distance_km = $this->calculateDistance(
                        (float) $patientLat,
                        (float) $patientLng,
                        (float) $nakes->latitude,
                        (float) $nakes->longitude
                    );
                    return $nakes;
                })
                ->sortBy('distance_km')
                ->values();
        }

        return $nakesList;
    }

    /**
     * API Pasien: Mendapatkan daftar Tenaga Medis terdekat.
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
     * Step 1: API Pasien - Membuat booking baru.
     * POST /api/booking
     */
    public function store(Request $request)
    {
        if ($request->has('payment_type')) {
            return $this->charge($request);
        }

        // ── Validasi input ────────────────────────────────────────────────────
        // Mendukung dua format:
        //   (a) layanan_ids: [1, 2, 3]   ← format multi-layanan baru
        //   (b) id_layanan: 1             ← format lama (single), untuk backward compat
        $validate = $request->validate([
            'layanan_ids' => 'nullable|array|min:1',
            'layanan_ids.*' => 'integer|exists:master_layanan,id_layanan',
            'id_layanan' => 'nullable',          // backward compat
            'id_kategori_tarif' => 'nullable|exists:master_kategori_tarif,id_kategori_tarif',
            'id_tenaga_medis' => 'nullable',
            'tanggal_kunjungan' => 'required|date',
            'jam_kunjungan' => 'required',
            'alamat_kunjungan' => 'nullable|string',
            'latitude_kunjungan' => 'nullable|numeric',
            'longitude_kunjungan' => 'nullable|numeric',
            'catatan' => 'nullable|string',
            'id_promo' => 'nullable',
            'id_kota' => 'nullable',
        ]);

        // ── Normalisasi: susun daftar layanan_ids ─────────────────────────────
        if (!empty($validate['layanan_ids'])) {
            $layananIds = array_values(array_unique(array_map('intval', $validate['layanan_ids'])));
        } else {
            // Fallback jika dikirim via id_layanan (bisa berupa array [10, 11] atau int tunggal)
            $raw = $validate['id_layanan'] ?? null;
            if (is_array($raw)) {
                $layananIds = array_values(array_unique(array_map('intval', $raw)));
            } elseif ($raw !== null && $raw !== '') {
                $layananIds = [(int) $raw];
            } else {
                $layananIds = [];
            }

            if (empty($layananIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layanan wajib dipilih. Gunakan layanan_ids[] atau id_layanan.',
                ], 422);
            }
        }

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

        // ── Load semua layanan yang dipesan ──────────────────────────────────
        $semuaLayanan = MasterLayanan::with(['masterTarif', 'bhpItems'])
            ->whereIn('id_layanan', $layananIds)
            ->get()
            ->keyBy('id_layanan');

        if ($semuaLayanan->count() !== count($layananIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Salah satu layanan tidak ditemukan.',
            ], 422);
        }

        // ── Resolve koordinat pasien ──────────────────────────────────────────
        $patientCoordinates = $this->resolveCoordinates(
            $validate['latitude_kunjungan'] ?? null,
            $validate['longitude_kunjungan'] ?? null,
            $alamatKunjungan
        );

        if (!$patientCoordinates) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat kunjungan tidak dapat ditemukan di peta. Periksa kembali alamatnya.'
            ], 422);
        }

        $patientLat = $patientCoordinates['latitude'];
        $patientLng = $patientCoordinates['longitude'];
        $validate['latitude_kunjungan'] = $patientLat;
        $validate['longitude_kunjungan'] = $patientLng;

        // ── Resolve tenaga medis & hitung jarak ──────────────────────────────
        $tenagaMedisId = $validate['id_tenaga_medis'] ?? null;
        if (is_array($tenagaMedisId)) {
            $tenagaMedisId = $tenagaMedisId[0] ?? null;
        }

        $distance = 0.0;
        $idKota = $request->input('id_kota');
        $idKategoriTarif = $request->input('id_kategori_tarif');

        // Layanan pertama dipakai untuk lookup nakes terdekat (jika tidak ada preferensi)
        $idLayananPrimary = $layananIds[0];

        if ($tenagaMedisId) {
            $tenagaMedis = TenagaMedis::where('status', 'approved')->find($tenagaMedisId);
            if (!$tenagaMedis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenaga medis yang dipilih tidak aktif atau tidak ditemukan.'
                ], 422);
            }
            if ($tenagaMedis->latitude && $tenagaMedis->longitude) {
                $distance = $this->calculateDistance(
                    $patientLat,
                    $patientLng,
                    (float) $tenagaMedis->latitude,
                    (float) $tenagaMedis->longitude
                );
            }
        } else {
            $nearestList = $this->findNearestNakes($patientLat, $patientLng, $idLayananPrimary);
            $nearestNakes = $nearestList->first();
            if ($nearestNakes) {
                $distance = (float) ($nearestNakes->distance_km ?? 0.0);
                if ($distance > 1000)
                    $distance = 0.0;
            }
            $tenagaMedisId = null;
        }

        // ── Helper: cari master tarif untuk satu layanan ─────────────────────
        $cariMasterTarif = function (int $idLayananTarget) use ($idKota, $idKategoriTarif): ?MasterTarif {
            $matchLayanan = function ($q) use ($idLayananTarget) {
                $q->where('id_layanan', $idLayananTarget)
                    ->orWhereHas('layananTermasuk', function ($q2) use ($idLayananTarget) {
                        $q2->where('master_layanan.id_layanan', $idLayananTarget);
                    });
            };

            $query = MasterTarif::with(['komponenTarif', 'kategoriTarif', 'layananTermasuk'])
                ->where('is_active', true)
                ->where($matchLayanan);

            if ($idKategoriTarif) {
                $query->where('id_kategori_tarif', $idKategoriTarif);
            }

            // Coba kota dulu, lalu fallback nasional
            $tarif = (clone $query)->when($idKota, fn($q) => $q->where('id_kota', $idKota))->first();
            if (!$tarif) {
                $tarif = (clone $query)->whereNull('id_kota')->first();
            }
            if (!$tarif) {
                $tarif = MasterTarif::with(['komponenTarif', 'kategoriTarif'])
                    ->where('is_active', true)
                    ->where($matchLayanan)
                    ->first();
            }
            return $tarif;
        };

        // ── Kalkulasi per-layanan ─────────────────────────────────────────────
        $kategoriTarifObj = $idKategoriTarif
            ? MasterKategoriTarif::find($idKategoriTarif)
            : null;

        // Akan diisi dari master tarif layanan pertama (untuk komponen admin/PPN)
        $masterTarifPrimary = null;

        // Akumulator total
        $totalSl = 0.0;
        $totalSb = 0.0;
        $totalHppBhp = 0.0;
        $totalFeeNakesBase = 0.0;  // tanpa transport

        // Array per-layanan untuk disimpan ke booking_layanan
        $perLayananData = [];

        foreach ($layananIds as $urutan => $idLyn) {
            /** @var MasterLayanan $layanan */
            $layanan = $semuaLayanan->get($idLyn);
            $masterTarif = $cariMasterTarif($idLyn);

            // Simpan master tarif layanan pertama untuk komponen global nanti
            if ($urutan === 0) {
                $masterTarifPrimary = $masterTarif;

                // Jika kategori tarif belum ada dari request, ambil dari master tarif pertama
                if (!$kategoriTarifObj) {
                    $kategoriTarifObj = $masterTarif?->kategoriTarif
                        ?? MasterKategoriTarif::where('is_default', true)->first();
                }
            }

            // SL: harga layanan + biaya tambahan kategori tarif (berlaku sama untuk semua layanan)
            $sl = (float) $layanan->harga;
            if ($kategoriTarifObj && (float) $kategoriTarifObj->biaya_tambahan > 0) {
                $sl += (float) $kategoriTarifObj->biaya_tambahan;
            }

            // SB & HPP BHP layanan ini
            $sb = 0.0;
            $hppBhp = 0.0;
            foreach ($layanan->bhpItems as $bhpItem) {
                $qty = (int) ($bhpItem->pivot->qty_default ?? 1);
                $sb += (float) $bhpItem->harga_jual * $qty;
                $hppBhp += (float) $bhpItem->harga_modal * $qty;
            }

            // Fee Nakes layanan ini (tanpa transport)
            $feeType = $masterTarif?->fee_nakes_tipe ?? 'persen';
            $feeVal = (float) ($masterTarif?->fee_nakes_nilai ?? 80.0);

            if ($feeType === 'nominal') {
                $feeNakesLyn = (float) ($masterTarif?->fee_nakes_nominal > 0
                    ? $masterTarif->fee_nakes_nominal
                    : $feeVal);
            } else {
                $feeNakesLyn = $sl * ($feeVal / 100);
            }

            $totalSl += $sl;
            $totalSb += $sb;
            $totalHppBhp += $hppBhp;
            $totalFeeNakesBase += $feeNakesLyn;

            $perLayananData[] = [
                'id_layanan' => $idLyn,
                'urutan' => $urutan + 1,
                'sl' => round($sl, 2),
                'sb' => round($sb, 2),
                'hpp_bhp' => round($hppBhp, 2),
                'hak_nakes_layanan' => round($feeNakesLyn, 2),
            ];
        }

        // ── Transport (dihitung sekali — satu kunjungan) ─────────────────────
        // Transport tidak dikenakan jika SEMUA layanan include transport
        $semuaIncludeTransport = $semuaLayanan->every(fn($l) => (bool) $l->include_transport);
        $tarifTransportasiFinal = 0.0;

        if (!$semuaIncludeTransport) {
            $transportMaster = $idKota
                ? MasterTarifTransport::where('id_kota', $idKota)->first()
                : null;

            if ($transportMaster) {
                $tarifTransportasiFinal = (float) $transportMaster->tarif_awal
                    + ($distance * (float) $transportMaster->tarif_per_kilometer);
            } else {
                $tarifTransportasiFinal = $distance > 0 ? (10000.0 + ($distance * 3000.0)) : 0.0;
            }
        }

        // ── Komponen Tarif global (admin/PPN) — dari master tarif layanan pertama ──
        $biayaAdministrasiAplikasi = 0.0;
        $persentasePpnPajak = 0.0;
        $nominalPpnPajak = 0.0;

        $komponenList = ($masterTarifPrimary && $masterTarifPrimary->komponenTarif->isNotEmpty())
            ? $masterTarifPrimary->komponenTarif
            : \App\Models\MasterKomponenBiaya::where('is_active', true)->get();

        foreach ($komponenList as $komponen) {
            if (!$komponen->is_active)
                continue;

            if (in_array($komponen->tipe_komponen, ['admin_aplikasi', 'lainnya'])) {
                if ($komponen->jenis_nilai === 'nominal') {
                    $biayaAdministrasiAplikasi += (float) $komponen->nilai;
                } elseif ($komponen->jenis_nilai === 'persen') {
                    // Persentase dihitung dari total SL seluruh layanan
                    $biayaAdministrasiAplikasi += $totalSl * ((float) $komponen->nilai / 100);
                }
            } elseif ($komponen->tipe_komponen === 'pajak') {
                if ($komponen->jenis_nilai === 'persen') {
                    $persentasePpnPajak += (float) $komponen->nilai;
                } elseif ($komponen->jenis_nilai === 'nominal') {
                    $nominalPpnPajak += (float) $komponen->nilai;
                }
            }
        }

        if ($persentasePpnPajak > 0 && $nominalPpnPajak == 0) {
            $nominalPpnPajak = ($totalSl + $totalSb + $tarifTransportasiFinal) * ($persentasePpnPajak / 100);
        }

        // ── Hak Nakes total = sum fee per layanan + transport ────────────────
        $nominalHakNakes = $totalFeeNakesBase + $tarifTransportasiFinal;

        // Persentase rata-rata fee nakes (untuk disimpan di transaksi, informatif)
        $persentaseBagianNakes = $totalSl > 0
            ? min(100, ($totalFeeNakesBase / $totalSl) * 100)
            : 80.0;

        // ── Rounding ke IDR ──────────────────────────────────────────────────
        $tarifLayananJasaMedis = (int) round($totalSl);
        $tarifBahanHabisPakai = (int) round($totalSb);
        $tarifTransportasiFinal = (int) round($tarifTransportasiFinal);
        $biayaAdministrasiAplikasi = (int) round($biayaAdministrasiAplikasi);
        $nominalPpnPajak = (int) round($nominalPpnPajak);
        $totalHppBhp = round($totalHppBhp, 2);
        $nominalHakNakes = round($nominalHakNakes, 2);

        $totalTagihanPasien = $tarifLayananJasaMedis + $tarifBahanHabisPakai
            + $tarifTransportasiFinal + $biayaAdministrasiAplikasi + $nominalPpnPajak;

        $feeMidtrans = (float) env('FEE_MIDTRANS', 4000.0);
        $estimasiProfitHomeCare = ($tarifLayananJasaMedis - $totalFeeNakesBase)
            + ($tarifBahanHabisPakai - $totalHppBhp)
            + $biayaAdministrasiAplikasi - $feeMidtrans;

        // ── Simpan ke DB (dalam lock + transaksi) ────────────────────────────
        try {
            return Cache::lock('create_booking_lock', 10)->block(5, function () use ($validate, $pasien, $layananIds, $semuaLayanan, $perLayananData, $tenagaMedisId, $alamatKunjungan, $totalTagihanPasien, $tarifLayananJasaMedis, $tarifBahanHabisPakai, $tarifTransportasiFinal, $biayaAdministrasiAplikasi, $nominalPpnPajak, $persentasePpnPajak, $persentaseBagianNakes, $feeMidtrans, $totalHppBhp, $nominalHakNakes, $estimasiProfitHomeCare, $distance) {
                return DB::transaction(function () use ($validate, $pasien, $layananIds, $semuaLayanan, $perLayananData, $tenagaMedisId, $alamatKunjungan, $totalTagihanPasien, $tarifLayananJasaMedis, $tarifBahanHabisPakai, $tarifTransportasiFinal, $biayaAdministrasiAplikasi, $nominalPpnPajak, $persentasePpnPajak, $persentaseBagianNakes, $feeMidtrans, $totalHppBhp, $nominalHakNakes, $estimasiProfitHomeCare, $distance) {

                    // 1. Generate booking_code (Format: B-YYMMDDXXXXXXX)
                    $prefixBooking = 'B-' . date('ymd');
                    $lastBookingToday = Booking::where('booking_code', 'LIKE', $prefixBooking . '%')
                        ->orderBy('id_booking', 'desc')
                        ->lockForUpdate()
                        ->first();

                    $nextSequence = $lastBookingToday
                        ? ((int) substr($lastBookingToday->booking_code, -7)) + 1
                        : 1;

                    $bookingCode = $prefixBooking . str_pad($nextSequence, 7, '0', STR_PAD_LEFT);

                    // 2. Generate medical_record_number
                    $medicalRecordNumber = Booking::generateMedicalRecordNumber();

                    // 3. Simpan Booking — id_layanan diisi layanan pertama (backward compat)
                    $booking = Booking::create([
                        'booking_code' => $bookingCode,
                        'medical_record_number' => $medicalRecordNumber,
                        'id_pasien' => $pasien->id_pasien,
                        'id_layanan' => $layananIds[0],   // primary layanan
                        'id_tenaga_medis' => $tenagaMedisId,
                        'tanggal_kunjungan' => $validate['tanggal_kunjungan'],
                        'jam_kunjungan' => $validate['jam_kunjungan'],
                        'alamat_kunjungan' => $alamatKunjungan,
                        'latitude_kunjungan' => $validate['latitude_kunjungan'],
                        'longitude_kunjungan' => $validate['longitude_kunjungan'],
                        'status_booking' => 'Pending',
                    ]);

                    if ($booking->id_tenaga_medis) {
                        app(WebSocketController::class)->ensureChatRoom($booking->load(['pasien', 'tenagaMedis']));
                    }

                    // 4. Simpan detail per-layanan ke booking_layanan
                    foreach ($perLayananData as $item) {
                        BookingLayanan::create([
                            'id_booking' => $booking->id_booking,
                            'id_layanan' => $item['id_layanan'],
                            'urutan' => $item['urutan'],
                            'sl' => $item['sl'],
                            'sb' => $item['sb'],
                            'hpp_bhp' => $item['hpp_bhp'],
                            'hak_nakes_layanan' => $item['hak_nakes_layanan'],
                        ]);
                    }

                    // 5. Simpan Transaksi
                    $orderId = 'BOOKING-' . $booking->id_booking . '-' . time();

                    Transaksi::create([
                        'id_booking' => $booking->id_booking,
                        'midtrans_order_id' => $orderId,
                        'jumlah_total' => $totalTagihanPasien,
                        'metode_pembayaran' => 'Pending',
                        'status_transaksi' => 'Belum Bayar',
                        'sl' => $tarifLayananJasaMedis,
                        'sb' => $tarifBahanHabisPakai,
                        'st' => $tarifTransportasiFinal,
                        'ba' => $biayaAdministrasiAplikasi,
                        'ppn' => $nominalPpnPajak,
                        'persen_ppn' => $persentasePpnPajak,
                        'persen_fee_nakes' => $persentaseBagianNakes,
                        'fee_midtrans' => $feeMidtrans,
                        'hpp_bhp' => $totalHppBhp,
                        'hak_nakes' => $nominalHakNakes,
                        'profit_hc' => $estimasiProfitHomeCare,
                    ]);

                    // 6. Bangun info layanan untuk response
                    $layananResponse = array_map(function ($item) use ($semuaLayanan) {
                        $l = $semuaLayanan->get($item['id_layanan']);
                        return [
                            'id_layanan' => $item['id_layanan'],
                            'nama_layanan' => $l?->nama_layanan,
                            'sl' => (int) round($item['sl']),
                            'sb' => (int) round($item['sb']),
                        ];
                    }, $perLayananData);

                    return response()->json([
                        'success' => true,
                        'message' => 'Booking berhasil dibuat. Silakan lanjutkan ke pemilihan metode pembayaran.',
                        'data' => [
                            'id_booking' => $booking->id_booking,
                            'booking_code' => $booking->booking_code,
                            'medical_record_number' => $booking->medical_record_number,
                            'order_id' => $orderId,
                            'layanan' => $layananResponse,
                            'rincian_biaya' => [
                                'total_sl' => $tarifLayananJasaMedis,
                                'total_sb' => $tarifBahanHabisPakai,
                                'st' => $tarifTransportasiFinal,
                                'ba' => $biayaAdministrasiAplikasi,
                                'ppn' => $nominalPpnPajak,
                            ],
                            'jumlah_total' => $totalTagihanPasien,
                            'distance_km' => round($distance, 2),
                        ],
                    ], 201);
                });
            });
        } catch (LockTimeoutException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server sedang sibuk memproses booking lain. Silakan coba beberapa detik lagi.',
            ], 429);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat booking: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Step 2: API Direct Midtrans Charge (Eksekusi Pembayaran via Core API)
     * POST /api/booking/charge
     */
    public function charge(Request $request)
    {
        $request->validate([
            'id_booking' => 'required|exists:bookings,id_booking',
            'payment_type' => 'required|string|in:qris,bank_transfer,gopay,shopeepay',
            'bank_transfer' => 'required_if:payment_type,bank_transfer|array',
            'qris' => 'nullable|array',
        ]);

        $booking = Booking::with(['transaksi', 'pasien.user'])->find($request->input('id_booking'));

        if (!$booking || !$booking->transaksi) {
            return response()->json([
                'success' => false,
                'message' => 'Booking atau data transaksi tidak ditemukan.'
            ], 404);
        }

        $transaksi = $booking->transaksi;
        $orderId = $transaksi->midtrans_order_id ?? ('BOOKING-' . $booking->id_booking . '-' . time());
        $grossAmount = (int) $transaksi->jumlah_total;

        $payload = [
            'payment_type' => $request->input('payment_type'),
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $booking->pasien?->nama_lengkap ?? 'Pasien',
                'email' => $booking->pasien?->user?->email ?? 'no-reply@example.com',
            ],
            'custom_expiry' => [
                'expiry_duration' => (int) env('MIDTRANS_EXPIRY_DURATION', 15),
                'unit' => env('MIDTRANS_EXPIRY_UNIT', 'minutes'),
            ],
        ];

        if ($request->input('payment_type') === 'bank_transfer') {
            $payload['bank_transfer'] = $request->input('bank_transfer');
        }

        if (in_array($request->input('payment_type'), ['qris', 'gopay'])) {
            $payload['qris'] = $request->input('qris', ['acquirer' => 'gopay']);
        }

        $serverKey = config('services.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY');
        $isProduction = config('services.midtrans.is_production', false);
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

            $response = $client->post($url, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                $paymentDetails = [
                    'midtrans_transaction_id' => $responseData['transaction_id'] ?? null,
                    'midtrans_order_id' => $responseData['order_id'] ?? $orderId,
                    'payment_method' => $request->input('payment_type'),
                    'midtrans_response' => $responseData,
                ];

                if ($request->input('payment_type') === 'bank_transfer' && isset($responseData['va_numbers'])) {
                    $vaArray = $responseData['va_numbers'];
                    if (is_array($vaArray) && !empty($vaArray)) {
                        $vaData = reset($vaArray);
                        $paymentDetails['va_number'] = $vaData['va_number'] ?? null;
                        $paymentDetails['bank_va'] = $vaData['bank'] ?? null;
                    }
                }

                if (in_array($request->input('payment_type'), ['qris', 'gopay']) && isset($responseData['qr_string'])) {
                    $paymentDetails['qr_string'] = $responseData['qr_string'];

                    if (isset($responseData['actions'])) {
                        foreach ($responseData['actions'] as $action) {
                            if (($action['name'] ?? '') === 'generate-qr-code') {
                                $paymentDetails['qr_url'] = $action['url'];
                                break;
                            }
                        }
                    }
                }

                $transaksi->update($paymentDetails);
            }

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi charge Midtrans berhasil dibuat.',
                    'data' => array_merge($responseData, [
                        'id_booking' => $booking->id_booking,
                        'order_id' => $responseData['order_id'] ?? $orderId,
                        'jumlah_total' => (float) $transaksi->jumlah_total,
                        'jumlah_total_format' => 'Rp ' . number_format((float) $transaksi->jumlah_total, 0, ',', '.'),
                        'va_number' => $transaksi->va_number,
                        'bank_va' => $transaksi->bank_va,
                        'qr_string' => $transaksi->qr_string,
                        'qr_url' => $transaksi->qr_url,
                    ])
                ], $response->status());
            }

            return response()->json([
                'success' => false,
                'message' => $responseData['status_message'] ?? 'Gagal membuat charge pembayaran Midtrans.',
                'error' => $responseData
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal meneruskan pembayaran ke Midtrans: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Detail Booking berdasarkan ID
     */
    public function show($id)
    {
        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail booking',
            'data' => new BookingResource($booking),
        ]);
    }

    /**
     * API Menampilkan Payment Details untuk Pembayaran
     * GET /api/booking/{id}/payment-details
     */
    public function getPaymentDetails($id)
    {
        $booking = Booking::with(['layananItems.layanan', 'transaksi'])->find($id);

        if (!$booking || !$booking->transaksi) {
            return response()->json([
                'success' => false,
                'message' => 'Data booking atau transaksi tidak ditemukan.'
            ], 404);
        }

        $transaksi = $booking->transaksi;
        $orderId = $transaksi->midtrans_order_id ?? ('BOOKING-' . $booking->id_booking);
        $lunasStatuses = ['lunas', 'sudah bayar', 'settlement', 'success'];

        if (!in_array(strtolower($transaksi->status_transaksi), $lunasStatuses) && $orderId) {
            $serverKey = config('services.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY');
            $baseUrl = config('services.midtrans.is_production', false)
                ? 'https://api.midtrans.com/v2/'
                : 'https://api.sandbox.midtrans.com/v2/';

            try {
                $response = Http::withBasicAuth($serverKey, '')
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->withoutVerifying()
                    ->get($baseUrl . $orderId . '/status');

                if ($response->successful()) {
                    $midtransData = $response->json();
                    $transactionStatus = $midtransData['transaction_status'] ?? '';

                    if (in_array($transactionStatus, ['settlement', 'capture'])) {
                        $transaksi->update([
                            'status_transaksi' => 'Lunas',
                            'waktu_bayar' => $midtransData['settlement_time'] ?? now(),
                        ]);
                        $transaksi->refresh();
                    }
                }
            } catch (\Throwable $e) {
                // Ignore network errors
            }
        }

        if (in_array(strtolower($transaksi->status_transaksi), $lunasStatuses)) {
            return response()->json([
                'success' => true,
                'message' => 'Pembayaran untuk booking ini sudah lunas.',
                'data' => [
                    'status_transaksi' => $transaksi->status_transaksi,
                    'waktu_bayar' => $transaksi->waktu_bayar,
                ]
            ], 200);
        }

        $paymentDetails = [];
        $jumlahTotal = (float) $transaksi->jumlah_total;
        $jumlahFormat = 'Rp ' . number_format($jumlahTotal, 0, ',', '.');

        if ($transaksi->payment_method === 'qris' || ($transaksi->qr_string && $transaksi->qr_url)) {
            $paymentDetails['qris'] = [
                'qr_string' => $transaksi->qr_string,
                'qr_url' => $transaksi->qr_url,
                'jumlah' => $jumlahTotal,
                'jumlah_format' => $jumlahFormat,
            ];
        } elseif ($transaksi->payment_method === 'bank_transfer' || ($transaksi->va_number && $transaksi->bank_va)) {
            $paymentDetails['virtual_account'] = [
                'va_number' => $transaksi->va_number,
                'bank' => strtoupper($transaksi->bank_va),
                'jumlah' => $jumlahTotal,
                'jumlah_format' => $jumlahFormat,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => [
                'booking_code' => $booking->booking_code,
                'order_id' => $orderId,
                'status_transaksi' => $transaksi->status_transaksi,
                'jumlah_total' => $jumlahTotal,
                'jumlah_total_format' => $jumlahFormat,
                'payment_details' => $paymentDetails,
                'created_at' => $booking->created_at,
            ]
        ], 200);
    }

    /**
     * API Laporan / Ringkasan Transaksi satu Booking.
     */
    public function laporan($id)
    {
        $booking = Booking::with([
            'pasien',
            'layanan',
            'tenagaMedis',
            'transaksi',
            'layananItems.layanan',   // ← multi-layanan detail
        ])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $t = $booking->transaksi;

        // Rincian per-layanan (hanya ada jika booking multi-layanan)
        $rincianPerLayanan = $booking->layananItems->map(fn($item) => [
            'nama_layanan' => $item->layanan?->nama_layanan,
            'urutan' => $item->urutan,
            'sl' => (float) $item->sl,
            'sl_format' => 'Rp ' . number_format((float) $item->sl, 0, ',', '.'),
            'sb' => (float) $item->sb,
            'sb_format' => 'Rp ' . number_format((float) $item->sb, 0, ',', '.'),
            'hak_nakes_layanan' => (float) $item->hak_nakes_layanan,
        ])->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Laporan transaksi booking',
            'data' => [
                'booking_code' => $booking->booking_code,
                'status_booking' => $booking->status_booking,
                'status_label' => (new BookingResource($booking))->resolve()['status_label'] ?? $booking->status_booking,
                'tanggal_kunjungan' => $booking->tanggal_kunjungan
                    ? Carbon::parse($booking->tanggal_kunjungan)->translatedFormat('l, d F Y')
                    : null,
                'jam_kunjungan' => $booking->jam_kunjungan,
                'dibuat_pada' => $booking->created_at
                    ? Carbon::parse($booking->created_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,
                'pasien' => [
                    'nama' => $booking->pasien?->nama_lengkap,
                    'alamat' => $booking->alamat_kunjungan,
                ],
                'tenaga_medis' => [
                    'nama' => $booking->tenagaMedis?->nama_lengkap,
                    'jenis' => $booking->tenagaMedis?->jenis_tenaga_medis,
                ],
                // Layanan utama (backward compat) + daftar semua layanan
                'layanan' => empty($rincianPerLayanan) && $booking->layanan ? [
                    'nama' => $booking->layanan?->nama_layanan,
                ] : null,
                'layanan_items' => $rincianPerLayanan,
                'jumlah_layanan' => count($rincianPerLayanan) ?: 1,
                // Rincian biaya agregat (total semua layanan)
                'rincian_biaya' => $t ? [
                    ['label' => 'Total Tarif Layanan (SL)', 'nilai' => (float) $t->sl, 'format' => 'Rp ' . number_format((float) $t->sl, 0, ',', '.')],
                    ['label' => 'Bahan Habis Pakai (SB)', 'nilai' => (float) $t->sb, 'format' => 'Rp ' . number_format((float) $t->sb, 0, ',', '.')],
                    ['label' => 'Bahan Habis Pakai Tambahan (SB Tambahan)', 'nilai' => (float) ($t->sb_tambahan ?? 0), 'format' => 'Rp ' . number_format((float) ($t->sb_tambahan ?? 0), 0, ',', '.')],
                    ['label' => 'Biaya Transportasi (ST)', 'nilai' => (float) $t->st, 'format' => 'Rp ' . number_format((float) $t->st, 0, ',', '.')],
                    ['label' => 'Biaya Admin Aplikasi', 'nilai' => (float) $t->ba, 'format' => 'Rp ' . number_format((float) $t->ba, 0, ',', '.')],
                    ['label' => 'PPN (' . (float) $t->persen_ppn . '%)', 'nilai' => (float) $t->ppn, 'format' => 'Rp ' . number_format((float) $t->ppn, 0, ',', '.')],
                ] : [],
                'jumlah_total' => $t ? (float) $t->jumlah_total : 0,
                'jumlah_total_format' => $t ? 'Rp ' . number_format((float) $t->jumlah_total, 0, ',', '.') : '-',
                'metode_pembayaran' => $t?->metode_pembayaran,
                'status_transaksi' => $t?->status_transaksi,
                'waktu_bayar' => $t?->waktu_bayar
                    ? Carbon::parse($t->waktu_bayar)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,
                'bagi_hasil' => $t ? [
                    'hak_nakes' => (float) $t->hak_nakes,
                    'hak_nakes_format' => 'Rp ' . number_format((float) $t->hak_nakes, 0, ',', '.'),
                    'profit_hc' => (float) $t->profit_hc,
                    'profit_hc_format' => 'Rp ' . number_format((float) $t->profit_hc, 0, ',', '.'),
                    'fee_midtrans' => (float) $t->fee_midtrans,
                    'hpp_bhp' => (float) $t->hpp_bhp,
                    'hpp_bhp_tambahan' => (float) ($t->hpp_bhp_tambahan ?? 0),
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

        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])->find($id);

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
            'data' => new BookingResource($booking->load(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])),
        ]);
    }

    /**
     * API Cek Status Transaksi & Booking
     */
    public function checkStatus($idTransaksi)
    {
        $transaksi = Transaksi::with([
            'booking.pasien',
            'booking.layanan',
            'booking.layananItems.layanan',
            'booking.tenagaMedis',
        ])->find($idTransaksi);

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
            'data' => [
                'id_transaksi' => $transaksi->id_transaksi,
                'id_booking' => $transaksi->id_booking,
                'booking_code' => $booking?->booking_code,
                'status_booking' => $booking?->status_booking,
                'status_transaksi' => $transaksi->status_transaksi,
                'metode_pembayaran' => $transaksi->metode_pembayaran,
                'jumlah_total' => (float) $transaksi->jumlah_total,
                'jumlah_total_format' => 'Rp ' . number_format((float) $transaksi->jumlah_total, 0, ',', '.'),
                'waktu_bayar' => $transaksi->waktu_bayar
                    ? Carbon::parse($transaksi->waktu_bayar)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,
                'dibuat_pada' => $booking?->created_at
                    ? Carbon::parse($booking->created_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                    : null,
                'booking_detail' => $booking ? new BookingResource($booking) : null,
            ],
        ]);
    }

    private function getLoggedNakes(Request $request): ?TenagaMedis
    {
        $user = $request->user();

        return $user
            ? TenagaMedis::with(['user', 'pasien', 'kategoriLayanan', 'jadwalKerja', 'wilayahLayanan'])
                ->where('id_user', $user->id_user)
                ->where('status', 'approved')
                ->first()
            : null;
    }

    private function isPaidTransaction(?Transaksi $transaksi): bool
    {
        return $transaksi
            && in_array(strtolower((string) $transaksi->status_transaksi), [
                'lunas',
                'sudah bayar',
                'settlement',
                'success',
            ], true);
    }

    private function hasRejectedOrder(Booking $booking, TenagaMedis $nakes): bool
    {
        $rejections = is_array($booking->catatan_penolakan)
            ? $booking->catatan_penolakan
            : json_decode($booking->catatan_penolakan ?? '[]', true);

        if (!is_array($rejections)) {
            return false;
        }

        foreach ($rejections as $rejection) {
            if ((int) ($rejection['id_tenaga_medis'] ?? 0) === (int) $nakes->id_tenaga_medis) {
                return true;
            }
        }

        return false;
    }

    private function timeRangeOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if (!$startA || !$endA || !$startB || !$endB) {
            return false;
        }

        try {
            $aStart = Carbon::parse('2000-01-01 ' . $startA);
            $aEnd = Carbon::parse('2000-01-01 ' . $endA);
            $bStart = Carbon::parse('2000-01-01 ' . $startB);
            $bEnd = Carbon::parse('2000-01-01 ' . $endB);

            return $aStart->lt($bEnd) && $bStart->lt($aEnd);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getNakesOrderEligibility(TenagaMedis $nakes, Booking $booking): array
    {
        $reasons = [];
        $distanceKm = 0.0;

        $booking->loadMissing(['layanan.kategori', 'layananItems.layanan.kategori', 'transaksi']);

        if (!$booking->transaksi) {
            $reasons[] = 'Booking belum memiliki transaksi.';
            return [
                'eligible' => false,
                'reasons' => $reasons,
                'distance_km' => 0.0,
            ];
        }

        // Cek jika Nakes sudah pernah menolak order ini
        $catatanPenolakan = is_array($booking->catatan_penolakan)
            ? $booking->catatan_penolakan
            : json_decode($booking->catatan_penolakan ?? '[]', true);

        if (is_array($catatanPenolakan)) {
            $rejectedNakesIds = array_column($catatanPenolakan, 'id_tenaga_medis');
            if (in_array((int) $nakes->id_tenaga_medis, array_map('intval', $rejectedNakesIds))) {
                $reasons[] = 'Anda sudah pernah menolak order ini.';
            }
        }

        if ($booking->id_tenaga_medis && (int) $booking->id_tenaga_medis !== (int) $nakes->id_tenaga_medis) {
            $reasons[] = 'Order sudah dialokasikan ke Tenaga Medis lain.';
        }

        $statusTransaksi = strtolower((string) $booking->transaksi->status_transaksi);
        if (!in_array($statusTransaksi, ['lunas', 'sudah bayar', 'settlement', 'success'])) {
            $reasons[] = 'Booking belum berhasil dibayar.';
        }

        if ($booking->status_booking !== 'Pending') {
            $reasons[] = 'Booking tidak dalam status menunggu.';
        }

        $layananItems = $booking->layananItems;
        $layananList = $layananItems->isNotEmpty()
            ? $layananItems->map(fn($item) => $item->layanan)->filter()
            : collect([$booking->layanan])->filter();

        $allowedKategoriIds = $nakes->kategoriLayanan()->pluck('kategori_layanans.id_kategori_layanan')->toArray();
        $operasional = \App\Models\OperasionalNakes::where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->where('status', 'approved')
            ->first();

        if ($operasional) {
            $allowedKategoriIds = array_unique(array_merge($allowedKategoriIds, (array) ($operasional->kategori_layanan ?? [])));
        }

        $requiredKategoriIds = $layananList
            ->pluck('id_kategori_layanan')
            ->filter()
            ->unique();

        if ($requiredKategoriIds->diff($allowedKategoriIds)->isNotEmpty()) {
            $reasons[] = 'Kategori salah satu layanan tidak sesuai dengan spesialisasi nakes.';
        }

        $bookingDate = $booking->tanggal_kunjungan ? Carbon::parse($booking->tanggal_kunjungan) : null;
        $bookingJam = $booking->jam_kunjungan;
        $hasSchedule = false;

        if ($bookingDate && $bookingJam) {
            $dayName = $bookingDate->locale('id')->translatedFormat('l');
            $bookingStart = Carbon::parse($bookingDate->format('Y-m-d') . ' ' . $bookingJam);
            $durasiMenit = max(1, (int) $layananList->sum(fn($item) => $item->durasi_menit ?? 60));
            $bookingEnd = $bookingStart->copy()->addMinutes($durasiMenit);

            foreach ($nakes->jadwalKerja as $jadwal) {
                if (($jadwal->hari ?? null) === $dayName) {
                    if ($this->timeRangeOverlap($jadwal->jam_mulai, $jadwal->jam_selesai, $bookingJam, $bookingEnd->format('H:i'))) {
                        $hasSchedule = true;
                        break;
                    }
                }
            }

            if (!$hasSchedule && $operasional) {
                foreach ($operasional->waktu_layanan ?? [] as $slot) {
                    if (($slot['hari'] ?? null) === $dayName) {
                        if ($this->timeRangeOverlap($slot['jam_mulai'] ?? null, $slot['jam_selesai'] ?? null, $bookingJam, $bookingEnd->format('H:i'))) {
                            $hasSchedule = true;
                            break;
                        }
                    }
                }
            }
        }

        if (!$hasSchedule) {
            $reasons[] = 'Jadwal nakes tidak tersedia pada waktu booking.';
        }

        $nakesCoordinates = $this->resolveCoordinates(
            $nakes->latitude,
            $nakes->longitude,
            $nakes->alamat_lengkap ?: $nakes->pasien?->alamat_utama
        );
        $bookingCoordinates = $this->resolveCoordinates(
            $booking->latitude_kunjungan,
            $booking->longitude_kunjungan,
            $booking->alamat_kunjungan
        );

        $this->storeMissingCoordinates($nakes, $nakesCoordinates);
        $this->storeMissingCoordinates(
            $booking,
            $bookingCoordinates,
            'latitude_kunjungan',
            'longitude_kunjungan'
        );

        if ($nakesCoordinates && $bookingCoordinates) {
            $distanceKm = $this->calculateDistance(
                $nakesCoordinates['latitude'],
                $nakesCoordinates['longitude'],
                $bookingCoordinates['latitude'],
                $bookingCoordinates['longitude']
            );

            if ($distanceKm > 30) {
                $reasons[] = 'Lokasi pasien berada di luar radius layanan 30 km.';
            }
        } else {
            if (!$nakesCoordinates) {
                $reasons[] = empty(trim((string) ($nakes->alamat_lengkap ?: $nakes->pasien?->alamat_utama)))
                    ? 'Alamat nakes wajib diisi.'
                    : 'Alamat nakes tidak dapat ditemukan di peta.';
            }

            if (!$bookingCoordinates) {
                $reasons[] = empty(trim((string) $booking->alamat_kunjungan))
                    ? 'Alamat pasien wajib diisi.'
                    : 'Alamat pasien tidak dapat ditemukan di peta.';
            }
        }

        // Cek Bentrok Order Nakes
        $bookingConflict = Booking::where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->whereDate('tanggal_kunjungan', $booking->tanggal_kunjungan)
            ->whereNotIn('status_booking', ['Selesai', 'Dibatalkan'])
            ->where('id_booking', '!=', $booking->id_booking)
            ->with(['layanan', 'layananItems.layanan'])
            ->get();

        $bookingStart = $booking->tanggal_kunjungan && $booking->jam_kunjungan
            ? Carbon::parse($booking->tanggal_kunjungan . ' ' . $booking->jam_kunjungan)
            : null;
        $durasiMenit = max(1, (int) $layananList->sum(fn($item) => $item->durasi_menit ?? 60));
        $bookingEnd = $bookingStart ? $bookingStart->copy()->addMinutes($durasiMenit) : null;

        foreach ($bookingConflict as $conflict) {
            if (!$conflict->jam_kunjungan || !$bookingStart || !$bookingEnd) {
                continue;
            }

            $conflictStart = Carbon::parse($conflict->tanggal_kunjungan . ' ' . $conflict->jam_kunjungan);
            $conflictLayananItems = $conflict->layananItems;
            $conflictLayananList = $conflictLayananItems->isNotEmpty()
                ? $conflictLayananItems->map(fn($item) => $item->layanan)->filter()
                : collect([$conflict->layanan])->filter();
            $conflictDuration = max(1, (int) $conflictLayananList->sum(fn($item) => $item->durasi_menit ?? 60));
            $conflictEnd = $conflictStart->copy()->addMinutes($conflictDuration);

            if ($bookingStart->lt($conflictEnd) && $conflictStart->lt($bookingEnd)) {
                $reasons[] = 'Nakes sudah memiliki order pada waktu yang sama.';
                break;
            }
        }

        return [
            'eligible' => empty($reasons),
            'reasons' => array_values(array_unique($reasons)),
            'distance_km' => round((float) $distanceKm, 2),
        ];
    }

    private function getNakesArrivalEstimate(TenagaMedis $nakes, Booking $booking): array
    {
        if (!$nakes->latitude || !$nakes->longitude || !$booking->latitude_kunjungan || !$booking->longitude_kunjungan) {
            return [
                'distance_km' => 0.0,
                'durasi_menit' => 0,
                'label' => 'Lokasi belum lengkap',
            ];
        }

        $distanceKm = $this->calculateDistance(
            (float) $nakes->latitude,
            (float) $nakes->longitude,
            (float) $booking->latitude_kunjungan,
            (float) $booking->longitude_kunjungan
        );

        $durasiMenit = max(10, (int) ceil(($distanceKm / 25) * 60));

        return [
            'distance_km' => round((float) $distanceKm, 2),
            'durasi_menit' => $durasiMenit,
            'label' => 'Sekitar ' . $durasiMenit . ' menit',
        ];
    }

    public function nakesOrderQueue(Request $request)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang aktif yang dapat melihat order.'
            ], 403);
        }

        $bookings = Booking::with(['pasien', 'layanan.kategori', 'layananItems.layanan.kategori', 'tenagaMedis', 'transaksi'])
            ->where('status_booking', 'Pending')
            ->whereHas('transaksi', function ($query) {
                $query->whereRaw("LOWER(status_transaksi) IN ('lunas', 'sudah bayar', 'settlement', 'success')");
            })
            ->orderByDesc('created_at')
            ->get();

        $bookings = $bookings
            ->reject(fn(Booking $booking) => $this->hasRejectedOrder($booking, $nakes))
            ->values();

        $eligibleOrders = [];
        $ineligibleOrders = [];

        foreach ($bookings as $booking) {
            $eligibility = $this->getNakesOrderEligibility($nakes, $booking);
            $arrive = $this->getNakesArrivalEstimate($nakes, $booking);
            $payload = [
                'booking' => new BookingResource($booking),
                'eligibility' => $eligibility,
                'estimasi_sampai' => $arrive,
            ];

            if ($eligibility['eligible']) {
                $eligibleOrders[] = $payload;
            } else {
                $ineligibleOrders[] = $payload;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar order booking yang siap ditawarkan ke nakes.',
            'total' => count($eligibleOrders),
            'data' => $eligibleOrders,
            'ineligible' => $ineligibleOrders,
        ]);
    }

    public function nakesOrderDetail(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang aktif yang dapat melihat detail order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan.kategori', 'layananItems.layanan.kategori', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ($this->hasRejectedOrder($booking, $nakes)) {
            return response()->json([
                'success' => false,
                'message' => 'Order ini sudah Anda tolak dan tidak tersedia lagi.',
            ], 404);
        }

        if (!$this->isPaidTransaction($booking->transaksi)) {
            return response()->json([
                'success' => false,
                'message' => 'Detail order belum tersedia karena pembayaran belum selesai.',
            ], 404);
        }

        $eligibility = $this->getNakesOrderEligibility($nakes, $booking);

        return response()->json([
            'success' => true,
            'message' => 'Detail order booking nakes.',
            'data' => [
                'booking' => new BookingResource($booking),
                'nakes' => [
                    'id_tenaga_medis' => $nakes->id_tenaga_medis,
                    'nama_lengkap' => $nakes->nama_lengkap,
                    'jenis_tenaga_medis' => $nakes->jenis_tenaga_medis,
                    'no_telp' => $nakes->no_telp,
                ],
                'assigned_nakes' => $booking->tenagaMedis ? [
                    'id_tenaga_medis' => $booking->tenagaMedis->id_tenaga_medis,
                    'nama_lengkap' => $booking->tenagaMedis->nama_lengkap,
                    'jenis_tenaga_medis' => $booking->tenagaMedis->jenis_tenaga_medis,
                ] : null,
                'eligibility' => $eligibility,
                'estimasi_sampai' => $this->getNakesArrivalEstimate($nakes, $booking),
            ],
        ]);
    }

    /**
     * API Nakes: Menampilkan daftar booking.
     */
    public function nakesIndex(Request $request)
    {
        $user = $request->user();
        $nakes = TenagaMedis::where('id_user', $user?->id_user)
            ->where('status', 'approved')
            ->first();

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
     * API Nakes: Terima Order Booking.
     * Mengkalkulasi ulang biaya transport Nakes penerima dan mencatat Biaya Tambahan jika jarak lebih jauh.
     */
    public function nakesAcceptBooking(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang dapat menerima order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan.kategori', 'layananItems.layanan.kategori', 'tenagaMedis', 'transaksi'])->find($id);

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

        $eligibility = $this->getNakesOrderEligibility($nakes, $booking);
        if (!$eligibility['eligible']) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak dapat diterima oleh nakes. Cek kriteria kategori, jadwal, area, atau konflik order.',
                'data' => [
                    'booking' => new BookingResource($booking),
                    'eligibility' => $eligibility,
                ],
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

            // Recalculate Biaya Transport untuk Nakes yang menerima
            $transaksi = $booking->transaksi;
            $nakesCoordinates = $this->resolveCoordinates(
                $nakes->latitude,
                $nakes->longitude,
                $nakes->alamat_lengkap ?: $nakes->pasien?->alamat_utama
            );
            $bookingCoordinates = $this->resolveCoordinates(
                $booking->latitude_kunjungan,
                $booking->longitude_kunjungan,
                $booking->alamat_kunjungan
            );

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
                    $idKota = $request->input('id_kota');
                    $transportMaster = $idKota ? MasterTarifTransport::where('id_kota', $idKota)->first() : null;
                    if ($transportMaster) {
                        $actualTransportCost = (float) $transportMaster->tarif_awal + ($actualDistance * (float) $transportMaster->tarif_per_kilometer);
                    } else {
                        $actualTransportCost = $actualDistance > 0 ? (10000.0 + ($actualDistance * 3000.0)) : 0.0;
                    }
                }
                $actualTransportCost = (int) round($actualTransportCost);
                $originalSt = (float) $transaksi->st;

                // Hitung jika ada biaya tambahan transport
                $biayaTambahan = max(0, $actualTransportCost - $originalSt);

                // Sesuaikan Hak Nakes (Nakes berhak mendapat biaya transport aktual)
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
                'data' => [
                    'booking' => new BookingResource($booking),
                    'eligibility' => $eligibility,
                    'estimasi_sampai' => $this->getNakesArrivalEstimate($nakes, $booking),
                ],
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
     * Jika transaksi sudah LUNAS, booking tidak dibatalkan melainkan kembali di-broadcast ke Nakes lain.
     */
    public function nakesRejectBooking(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang aktif yang dapat menolak order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'tenagaMedis', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ((int) $booking->id_tenaga_medis !== (int) $nakes->id_tenaga_medis && !is_null($booking->id_tenaga_medis)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk menolak order ini.'
            ], 403);
        }

        $statusTransaksi = strtolower((string) $booking->transaksi?->status_transaksi);
        $isPaid = in_array($statusTransaksi, ['lunas', 'sudah bayar', 'settlement', 'success']);

        // Catat Nakes yang menolak
        $catatanPenolakan = is_array($booking->catatan_penolakan)
            ? $booking->catatan_penolakan
            : json_decode($booking->catatan_penolakan ?? '[]', true);

        if (!is_array($catatanPenolakan)) {
            $catatanPenolakan = [];
        }

        $catatanPenolakan[] = [
            'id_tenaga_medis' => $nakes->id_tenaga_medis,
            'nama_nakes' => $nakes->nama_lengkap,
            'alasan' => $request->input('alasan', 'Nakes menolak order pada waktu ini.'),
            'rejected_at' => now()->toDateTimeString(),
        ];

        $booking->catatan_penolakan = json_encode($catatanPenolakan);

        if ($isPaid) {
            // Jika sudah LUNAS: kembalikan ke status Broadcast (Pending) agar Nakes lain bisa terima
            $booking->id_tenaga_medis = null;
            $booking->status_booking = 'Pending';
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil ditolak. Booking telah dikembalikan ke antrean broadcast untuk Nakes lain.',
                'data' => [
                    'booking' => new BookingResource($booking),
                    'alasan' => $request->input('alasan', 'Nakes menolak order pada waktu ini.'),
                ],
            ]);
        } else {
            // Jika BELUM BAYAR: batalkan booking sepenuhnya
            $booking->status_booking = 'Dibatalkan';
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil ditolak dan dibatalkan.',
                'data' => [
                    'booking' => new BookingResource($booking),
                    'alasan' => $request->input('alasan', 'Nakes menolak order pada waktu ini.'),
                ],
            ]);
        }
    }

    /**
     * API Nakes: Update Status Order.
     */
    public function nakesUpdateStatus(Request $request, $id)
    {
        $request->validate([
            'status_booking' => 'required|string|in:Pending,DiPerjalanan,Tindakan,Selesai,Dibatalkan',
        ]);

        $user = $request->user();
        $nakes = TenagaMedis::where('id_user', $user?->id_user)
            ->where('status', 'approved')
            ->first();

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Tenaga Medis yang dapat memperbarui status order.'
            ], 403);
        }

        $booking = Booking::with(['pasien', 'layanan', 'layananItems.layanan', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan.'
            ], 404);
        }

        if ((int) $booking->id_tenaga_medis !== (int) $nakes->id_tenaga_medis) {
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
            'data' => new BookingResource($booking->load(['pasien', 'layanan', 'tenagaMedis', 'transaksi'])),
        ]);
    }

    public function batalkanBooking($id)
    {
        $booking = Booking::with(['layananItems.layanan', 'transaksi'])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $booking->status_booking = 'Dibatalkan';
        $booking->save();

        $transaksi = $booking->transaksi;
        if ($transaksi && !in_array(strtolower($transaksi->status_transaksi), ['lunas', 'sudah bayar', 'settlement', 'success'])) {
            $orderId = $transaksi->midtrans_order_id;
            $serverKey = config('services.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY');
            $baseUrl = config('services.midtrans.is_production', false)
                ? 'https://api.midtrans.com/v2/'
                : 'https://api.sandbox.midtrans.com/v2/';

            try {
                Http::withBasicAuth($serverKey, '')
                    ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
                    ->withoutVerifying()
                    ->post($baseUrl . $orderId . '/cancel');
            } catch (\Throwable $e) {
                // Ignore network errors
            }

            $transaksi->status_transaksi = 'Dibatalkan';
            $transaksi->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking dan pembayaran berhasil dibatalkan.',
        ]);
    }
}