<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Controller untuk API Penghasilan Nakes.
 *
 * Semua data difilter dengan batas waktu 90 hari ke belakang
 * dihitung dari waktu_bayar transaksi (bukan tanggal booking).
 * Hanya transaksi berstatus "Lunas" yang dihitung.
 *
 * @group Nakes - Penghasilan
 */
class NakesPenghasilanController extends Controller
{
    /**
     * Batas lifetime data dalam hari.
     */
    private const LIFETIME_HARI = 90;

    // ──────────────────────────────────────────────────────────────────────
    // Helper: ambil TenagaMedis dari user yang login
    // ──────────────────────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────────────────────
    // Helper: base query booking milik nakes + lunas + dalam 90 hari
    // ──────────────────────────────────────────────────────────────────────

    private function baseQuery(TenagaMedis $nakes)
    {
        $batasWaktu = Carbon::now()->subDays(self::LIFETIME_HARI)->startOfDay();

        return Booking::with(['transaksi', 'pasien', 'layananItems.layanan'])
            ->where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->where('status_booking', 'Selesai')
            ->whereHas('transaksi', function ($q) use ($batasWaktu) {
                $q->where('status_transaksi', 'Lunas')
                  ->whereNotNull('waktu_bayar')
                  ->where('waktu_bayar', '>=', $batasWaktu);
            });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper: format nominal ke Rupiah
    // ──────────────────────────────────────────────────────────────────────

    private function rupiah(float $nominal): string
    {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper: hitung rincian penghasilan dari 1 transaksi
    // ──────────────────────────────────────────────────────────────────────

    private function rincianPenghasilan(Transaksi $transaksi): array
    {
        $sl           = (float) $transaksi->sl;
        $ongkir       = (float) $transaksi->st;
        $hakNakes     = (float) $transaksi->hak_nakes;
        $persenFee    = (float) $transaksi->persen_fee_nakes;

        // hak_nakes sudah termasuk ongkir, jadi fee jasa = hak_nakes - ongkir
        $feeJasa      = max(0, $hakNakes - $ongkir);

        // Potongan platform = sl (tarif jasa kotor) - feeJasa (bagian nakes dari jasa)
        $potongan     = max(0, $sl - $feeJasa);

        $keteranganPotongan = $persenFee > 0
            ? number_format(100 - $persenFee, 2, '.', '') . '% dari tarif jasa layanan'
            : 'Potongan platform';

        return [
            'tarif_jasa_kotor'        => $sl,
            'tarif_jasa_kotor_format' => $this->rupiah($sl),
            'ongkir'                  => $ongkir,
            'ongkir_format'           => $this->rupiah($ongkir),
            'potongan_platform'       => $potongan,
            'potongan_platform_format'=> $this->rupiah($potongan),
            'persen_fee_nakes'        => $persenFee,
            'keterangan_potongan'     => $keteranganPotongan,
            'penghasilan_bersih'      => $hakNakes,
            'penghasilan_bersih_format' => $this->rupiah($hakNakes),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Method: ringkasan harian / mingguan / bulanan
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Ringkasan Penghasilan Nakes
     *
     * Menampilkan total penghasilan bersih nakes dalam periode harian,
     * mingguan, dan bulanan. Data dibatasi maksimal 90 hari ke belakang
     * dihitung dari waktu_bayar transaksi.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Ringkasan penghasilan berhasil diambil",
     *   "data": {
     *     "periode_data": "90 hari terakhir",
     *     "batas_awal_data": "2026-07-24",
     *     "harian": {
     *       "label": "Hari Ini (Rabu, 22 Okt 2026)",
     *       "tanggal": "2026-10-22",
     *       "total_bersih": 350000,
     *       "total_bersih_format": "Rp 350.000",
     *       "jumlah_booking": 2
     *     },
     *     "mingguan": {
     *       "label": "Minggu Ini (19 Okt - 25 Okt 2026)",
     *       "dari": "2026-10-19",
     *       "sampai": "2026-10-25",
     *       "total_bersih": 1200000,
     *       "total_bersih_format": "Rp 1.200.000",
     *       "jumlah_booking": 7
     *     },
     *     "bulanan": {
     *       "label": "Bulan Ini (Oktober 2026)",
     *       "bulan": "2026-10",
     *       "total_bersih": 4500000,
     *       "total_bersih_format": "Rp 4.500.000",
     *       "jumlah_booking": 25
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Data tenaga medis tidak ditemukan."
     * }
     */
    public function ringkasan(Request $request)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga medis tidak ditemukan.',
            ], 404);
        }

        $now        = Carbon::now();
        $batasAwal  = $now->copy()->subDays(self::LIFETIME_HARI)->startOfDay();

        // ── Harian ────────────────────────────────────────────────────────
        $hariIni = $now->copy()->startOfDay();

        $queryHarian = $this->baseQuery($nakes)
            ->whereHas('transaksi', function ($q) use ($hariIni, $now) {
                $q->whereBetween('waktu_bayar', [$hariIni, $now->copy()->endOfDay()]);
            })
            ->get();

        $totalHarian  = $queryHarian->sum(fn($b) => (float) optional($b->transaksi)->hak_nakes);
        $countHarian  = $queryHarian->count();

        // ── Mingguan ──────────────────────────────────────────────────────
        $awalMinggu   = $now->copy()->startOfWeek(Carbon::MONDAY);
        $akhirMinggu  = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $queryMingguan = $this->baseQuery($nakes)
            ->whereHas('transaksi', function ($q) use ($awalMinggu, $akhirMinggu) {
                $q->whereBetween('waktu_bayar', [$awalMinggu, $akhirMinggu]);
            })
            ->get();

        $totalMingguan = $queryMingguan->sum(fn($b) => (float) optional($b->transaksi)->hak_nakes);
        $countMingguan = $queryMingguan->count();

        // ── Bulanan ───────────────────────────────────────────────────────
        $awalBulan  = $now->copy()->startOfMonth();
        $akhirBulan = $now->copy()->endOfMonth();

        $queryBulanan = $this->baseQuery($nakes)
            ->whereHas('transaksi', function ($q) use ($awalBulan, $akhirBulan) {
                $q->whereBetween('waktu_bayar', [$awalBulan, $akhirBulan]);
            })
            ->get();

        $totalBulanan = $queryBulanan->sum(fn($b) => (float) optional($b->transaksi)->hak_nakes);
        $countBulanan = $queryBulanan->count();

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan penghasilan berhasil diambil',
            'data'    => [
                'periode_data'    => self::LIFETIME_HARI . ' hari terakhir',
                'batas_awal_data' => $batasAwal->toDateString(),
                'harian'  => [
                    'label'               => 'Hari Ini (' . $now->translatedFormat('l, d M Y') . ')',
                    'tanggal'             => $hariIni->toDateString(),
                    'total_bersih'        => $totalHarian,
                    'total_bersih_format' => $this->rupiah($totalHarian),
                    'jumlah_booking'      => $countHarian,
                ],
                'mingguan' => [
                    'label'               => 'Minggu Ini (' . $awalMinggu->format('d M') . ' - ' . $akhirMinggu->format('d M Y') . ')',
                    'dari'                => $awalMinggu->toDateString(),
                    'sampai'              => $akhirMinggu->toDateString(),
                    'total_bersih'        => $totalMingguan,
                    'total_bersih_format' => $this->rupiah($totalMingguan),
                    'jumlah_booking'      => $countMingguan,
                ],
                'bulanan' => [
                    'label'               => 'Bulan Ini (' . $now->translatedFormat('F Y') . ')',
                    'bulan'               => $now->format('Y-m'),
                    'total_bersih'        => $totalBulanan,
                    'total_bersih_format' => $this->rupiah($totalBulanan),
                    'jumlah_booking'      => $countBulanan,
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Method: riwayat (list per booking, paginated)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Riwayat Penghasilan Nakes Per Booking
     *
     * Menampilkan daftar booking yang sudah lunas beserta rincian
     * penghasilan per booking, dibatasi 90 hari ke belakang dari
     * waktu_bayar transaksi.
     *
     * @authenticated
     *
     * @queryParam page integer Halaman. Default: 1. Example: 1
     * @queryParam per_page integer Jumlah per halaman (max 100). Default: 15. Example: 15
     * @queryParam tanggal_dari date Filter dari tanggal (Y-m-d). Example: 2026-10-01
     * @queryParam tanggal_sampai date Filter sampai tanggal (Y-m-d). Example: 2026-10-22
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Riwayat penghasilan berhasil diambil",
     *   "periode_data": "90 hari terakhir",
     *   "batas_awal_data": "2026-07-24",
     *   "pagination": {
     *     "total": 42,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "total_pages": 3,
     *     "has_more_pages": true
     *   },
     *   "data": []
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Data tenaga medis tidak ditemukan."
     * }
     */
    public function riwayat(Request $request)
    {
        $request->validate([
            'page'           => 'nullable|integer|min:1',
            'per_page'       => 'nullable|integer|min:1|max:100',
            'tanggal_dari'   => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari',
        ]);

        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga medis tidak ditemukan.',
            ], 404);
        }

        $batasAwal = Carbon::now()->subDays(self::LIFETIME_HARI)->startOfDay();
        $perPage   = $request->input('per_page', 15);

        $query = $this->baseQuery($nakes);

        // Filter opsional tanggal dari & sampai (berdasarkan waktu_bayar)
        if ($request->filled('tanggal_dari')) {
            $dari = Carbon::parse($request->input('tanggal_dari'))->startOfDay();
            // Tidak boleh sebelum batas 90 hari
            $dari = $dari->lt($batasAwal) ? $batasAwal : $dari;
            $query->whereHas('transaksi', fn($q) => $q->where('waktu_bayar', '>=', $dari));
        }

        if ($request->filled('tanggal_sampai')) {
            $sampai = Carbon::parse($request->input('tanggal_sampai'))->endOfDay();
            $query->whereHas('transaksi', fn($q) => $q->where('waktu_bayar', '<=', $sampai));
        }

        // Urutkan dari yang terbaru
        $query->orderByDesc(
            Transaksi::select('waktu_bayar')
                ->whereColumn('transaksis.id_booking', 'bookings.id_booking')
                ->limit(1)
        );

        $bookings = $query->paginate($perPage);

        $data = $bookings->map(function (Booking $booking) {
            $transaksi = $booking->transaksi;
            $layananList = $booking->layananItems->isNotEmpty()
                ? $booking->layananItems->pluck('layanan.nama_layanan')->filter()->values()
                : collect();

            return [
                'id_booking'             => $booking->id_booking,
                'booking_code'           => $booking->booking_code,
                'tanggal_kunjungan'      => $booking->tanggal_kunjungan,
                'waktu_bayar'            => optional($transaksi?->waktu_bayar)->toDateTimeString(),
                'nama_pasien'            => $booking->pasien?->nama_lengkap ?? '-',
                'layanan'                => $layananList,
                'rincian_penghasilan'    => $transaksi ? $this->rincianPenghasilan($transaksi) : null,
                'penghasilan_bersih'     => (float) optional($transaksi)->hak_nakes,
                'penghasilan_bersih_format' => $this->rupiah((float) optional($transaksi)->hak_nakes),
            ];
        });

        return response()->json([
            'success'         => true,
            'message'         => 'Riwayat penghasilan berhasil diambil',
            'periode_data'    => self::LIFETIME_HARI . ' hari terakhir',
            'batas_awal_data' => $batasAwal->toDateString(),
            'pagination'      => [
                'total'         => $bookings->total(),
                'count'         => $bookings->count(),
                'per_page'      => $bookings->perPage(),
                'current_page'  => $bookings->currentPage(),
                'total_pages'   => $bookings->lastPage(),
                'has_more_pages'=> $bookings->hasMorePages(),
                'from'          => $bookings->firstItem(),
                'to'            => $bookings->lastItem(),
            ],
            'data' => $data,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Method: detail penghasilan 1 booking by booking_code
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Detail Penghasilan 1 Booking
     *
     * Menampilkan rincian lengkap penghasilan nakes untuk satu booking
     * berdasarkan booking_code. Data hanya tersedia dalam 90 hari ke belakang
     * dihitung dari waktu_bayar.
     *
     * @authenticated
     *
     * @urlParam booking_code string required Kode booking. Example: B-261022-0000042
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Detail penghasilan booking berhasil diambil",
     *   "data": {
     *     "id_booking": 42,
     *     "booking_code": "B-261022-0000042",
     *     "status_booking": "Selesai",
     *     "tanggal_kunjungan": "2026-10-22",
     *     "jam_kunjungan": "09:00:00",
     *     "waktu_bayar": "2026-10-22 10:30:00",
     *     "nama_pasien": "John Doe",
     *     "alamat_kunjungan": "Jl. Contoh No. 1",
     *     "layanan": [
     *       { "nama_layanan": "Perawatan Luka", "urutan": 1 }
     *     ],
     *     "rincian_penghasilan": {
     *       "tarif_jasa_kotor": 200000,
     *       "tarif_jasa_kotor_format": "Rp 200.000",
     *       "ongkir": 15000,
     *       "ongkir_format": "Rp 15.000",
     *       "potongan_platform": 40000,
     *       "potongan_platform_format": "Rp 40.000",
     *       "persen_fee_nakes": 80.0,
     *       "keterangan_potongan": "20% dari tarif jasa layanan",
     *       "penghasilan_bersih": 175000,
     *       "penghasilan_bersih_format": "Rp 175.000"
     *     },
     *     "penghasilan_bersih": 175000,
     *     "penghasilan_bersih_format": "Rp 175.000"
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Data booking tidak ditemukan atau di luar periode 90 hari."
     * }
     */
    public function show(Request $request, string $booking_code)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga medis tidak ditemukan.',
            ], 404);
        }

        $batasAwal = Carbon::now()->subDays(self::LIFETIME_HARI)->startOfDay();

        $booking = Booking::with([
                'transaksi',
                'pasien',
                'layananItems.layanan',
                'bookingBhp.bhpItem',
            ])
            ->where('booking_code', $booking_code)
            ->where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->where('status_booking', 'Selesai')
            ->whereHas('transaksi', function ($q) use ($batasAwal) {
                $q->where('status_transaksi', 'Lunas')
                  ->whereNotNull('waktu_bayar')
                  ->where('waktu_bayar', '>=', $batasAwal);
            })
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Data booking tidak ditemukan atau di luar periode 90 hari.',
            ], 404);
        }

        $transaksi   = $booking->transaksi;
        $layananList = $booking->layananItems->map(fn($item) => [
            'nama_layanan' => $item->layanan?->nama_layanan ?? '-',
            'urutan'       => $item->urutan,
        ])->values();

        return response()->json([
            'success' => true,
            'message' => 'Detail penghasilan booking berhasil diambil',
            'data'    => [
                'id_booking'         => $booking->id_booking,
                'booking_code'       => $booking->booking_code,
                'status_booking'     => $booking->status_booking,
                'tanggal_kunjungan'  => $booking->tanggal_kunjungan,
                'jam_kunjungan'      => $booking->jam_kunjungan,
                'waktu_bayar'        => optional($transaksi?->waktu_bayar)->toDateTimeString(),
                'nama_pasien'        => $booking->pasien?->nama_lengkap ?? '-',
                'alamat_kunjungan'   => $booking->alamat_kunjungan,
                'layanan'            => $layananList,
                'rincian_penghasilan'=> $transaksi ? $this->rincianPenghasilan($transaksi) : null,
                'penghasilan_bersih' => (float) optional($transaksi)->hak_nakes,
                'penghasilan_bersih_format' => $this->rupiah((float) optional($transaksi)->hak_nakes),
            ],
        ]);
    }
}
