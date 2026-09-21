<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Transaksi;
use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LaporanController extends Controller
{
    /**
     * Laporan Rekapitulasi Transaksi Keuangan (Admin)
     *
     * @queryParam start_date string Tanggal awal filter (YYYY-MM-DD). Example: 2026-01-01
     * @queryParam end_date string Tanggal akhir filter (YYYY-MM-DD). Example: 2026-12-31
     * @queryParam status_transaksi string Status transaksi (Lunas, Pending, Gagal, Expired).
     * @queryParam metode_pembayaran string Filter metode pembayaran.
     * @queryParam per_page integer|string Jumlah data per halaman (default 15/all).
     */
    public function laporanTransaksi(Request $request)
    {
        $query = Transaksi::with(['booking.pasien', 'booking.layanan', 'booking.tenagaMedis']);

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('status_transaksi')) {
            $query->where('status_transaksi', $request->status_transaksi);
        }
        if ($request->filled('metode_pembayaran')) {
            $query->where('metode_pembayaran', $request->metode_pembayaran);
        }

        // Summary Agregat (Hanya hitung Lunas atau sesuai filter)
        $summaryQuery = clone $query;
        $totalTransaksiCount = $summaryQuery->count();

        $lunasQuery = (clone $query)->where(function ($q) {
            $q->where('status_transaksi', 'Lunas')
              ->orWhere('status_transaksi', 'settlement')
              ->orWhere('status_transaksi', 'success');
        });

        $totalPendapatan  = (float) $lunasQuery->sum('jumlah_total');
        $totalHakNakes    = (float) $lunasQuery->sum('hak_nakes');
        $totalProfitHc    = (float) $lunasQuery->sum('profit_hc');
        $totalLayanan     = (float) $lunasQuery->sum('sl');
        $totalBhp         = (float) $lunasQuery->sum('sb');
        $totalTransport   = (float) $lunasQuery->sum('st');
        $totalAdmin       = (float) $lunasQuery->sum('ba');
        $totalPpn         = (float) $lunasQuery->sum('ppn');

        $query->orderBy('created_at', 'desc');

        [$items, $pagination] = $this->paginateReport($query, $request);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil Laporan Transaksi',
            'summary' => [
                'total_transaksi'  => $totalTransaksiCount,
                'total_pendapatan' => $totalPendapatan,
                'total_hak_nakes'  => $totalHakNakes,
                'total_profit_hc'  => $totalProfitHc,
                'breakdown_biaya'  => [
                    'jasa_layanan' => $totalLayanan,
                    'bhp'          => $totalBhp,
                    'transport'    => $totalTransport,
                    'administrasi' => $totalAdmin,
                    'ppn'          => $totalPpn,
                ]
            ],
            'pagination' => $pagination,
            'data' => $items,
        ], 200);
    }

    /**
     * Laporan Rekapitulasi Booking / Pelayanan (Admin).
     * Mendukung filter tanggal, status booking/pembayaran, pasien, nakes,
     * layanan, kota, kategori tarif, metode pembayaran, pencarian, dan sorting.
     */
    public function laporanBooking(Request $request)
    {
        $validated = $request->validate([
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date', 'after_or_equal:tanggal_dari'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'dibuat_dari' => ['nullable', 'date'],
            'dibuat_sampai' => ['nullable', 'date', 'after_or_equal:dibuat_dari'],
            'status_booking' => ['nullable'],
            'status_pembayaran' => ['nullable', Rule::in(['berhasil', 'success', 'gagal', 'failed', 'pending'])],
            'status_transaksi' => ['nullable'],
            'id_pasien' => ['nullable', 'integer'],
            'id_tenaga_medis' => ['nullable', 'integer'],
            'id_layanan' => ['nullable', 'integer'],
            'id_kota' => ['nullable', 'integer'],
            'id_kategori_tarif' => ['nullable', 'integer'],
            'metode_pembayaran' => ['nullable', 'string', 'max:100'],
            'booking_code' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'sort_by' => [Rule::in(['created_at', 'tanggal_kunjungan', 'status_booking', 'booking_code'])],
            'sort_order' => [Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable'],
        ]);

        $query = Booking::with([
            'pasien', 'layanan', 'kategoriTarif', 'layananItems.layanan',
            'tenagaMedis', 'transaksi', 'transaksiTambahanTerakhir', 'bookingBhp.bhpItem',
        ]);

        $this->applyBookingReportFilters($query, $request, $validated);
        $summary = $this->bookingReportSummary($query);
        $query->orderBy($validated['sort_by'] ?? 'tanggal_kunjungan', $validated['sort_order'] ?? 'desc')
            ->orderBy('id_booking', 'desc');

        [$items, $pagination] = $this->paginateReport($query, $request, true);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil Laporan Booking',
            'filters' => $request->query(),
            'summary' => $summary,
            'pagination' => $pagination,
            'data' => $items,
        ], 200);
    }

    private function paginateReport($query, Request $request, bool $asBookingResource = false): array
    {
        $perPage = $request->input('per_page', 15);
        if ($perPage === 'all') {
            $items = $query->get();
            return [$asBookingResource ? BookingResource::collection($items) : $items, null];
        }

        $perPage = max(1, min(100, (int) $perPage));
        $items = $query->paginate($perPage)->appends($request->query());
        $data = $asBookingResource
            ? BookingResource::collection($items->items())
            : $items;

        return [$data, [
            'total' => $items->total(),
            'count' => $items->count(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'total_pages' => $items->lastPage(),
            'has_more_pages' => $items->hasMorePages(),
            'from' => $items->firstItem(),
            'to' => $items->lastItem(),
        ]];
    }

    private function applyBookingReportFilters($query, Request $request, array $validated): void
    {
        $this->whereInReport($query, 'status_booking', $request->input('status_booking'), [
            'Pending', 'DiPerjalanan', 'Tindakan', 'Selesai', 'Dibatalkan',
        ]);

        $tanggalDari = $validated['tanggal_dari'] ?? $validated['start_date'] ?? null;
        $tanggalSampai = $validated['tanggal_sampai'] ?? $validated['end_date'] ?? null;
        if ($tanggalDari) $query->whereDate('tanggal_kunjungan', '>=', $tanggalDari);
        if ($tanggalSampai) $query->whereDate('tanggal_kunjungan', '<=', $tanggalSampai);
        if ($request->filled('dibuat_dari')) $query->whereDate('created_at', '>=', $validated['dibuat_dari']);
        if ($request->filled('dibuat_sampai')) $query->whereDate('created_at', '<=', $validated['dibuat_sampai']);
        if ($request->filled('bulan')) $query->whereMonth('tanggal_kunjungan', $validated['bulan']);
        if ($request->filled('tahun')) $query->whereYear('tanggal_kunjungan', $validated['tahun']);

        foreach (['id_pasien', 'id_tenaga_medis', 'id_kota', 'id_kategori_tarif'] as $column) {
            if ($request->filled($column)) $query->where($column, $validated[$column]);
        }

        if ($request->filled('id_layanan')) {
            $idLayanan = $validated['id_layanan'];
            $query->where(fn($booking) => $booking->where('id_layanan', $idLayanan)
                ->orWhereHas('layananItems', fn($items) => $items->where('id_layanan', $idLayanan)));
        }

        if ($request->filled('status_transaksi')) {
            $this->whereInReport($query, 'status_transaksi', $request->input('status_transaksi'), null, 'transaksi');
        }
        if ($request->filled('status_pembayaran')) {
            $statuses = match ($validated['status_pembayaran']) {
                'berhasil', 'success' => ['Lunas', 'sudah bayar', 'settlement', 'success', 'capture'],
                'gagal', 'failed' => ['Gagal', 'gagal', 'Dibatalkan', 'cancel', 'deny', 'expire', 'expired'],
                default => ['Belum Bayar', 'Pending'],
            };
            $query->whereHas('transaksi', fn($transaction) => $transaction->whereIn('status_transaksi', $statuses));
        }
        if ($request->filled('metode_pembayaran')) {
            $method = $validated['metode_pembayaran'];
            $query->whereHas('transaksi', fn($transaction) => $transaction
                ->where('metode_pembayaran', $method)->orWhere('payment_method', $method));
        }
        if ($request->filled('booking_code')) $query->where('booking_code', 'like', '%' . $validated['booking_code'] . '%');

        if ($request->filled('search')) {
            $search = trim($validated['search']);
            $query->where(fn($booking) => $booking->where('booking_code', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn($patient) => $patient->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")->orWhere('no_telp', 'like', "%{$search}%"))
                ->orWhereHas('tenagaMedis', fn($nakes) => $nakes->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('no_telp', 'like', "%{$search}%"))
                ->orWhereHas('layananItems.layanan', fn($service) => $service->where('nama_layanan', 'like', "%{$search}%"))
                ->orWhereHas('layanan', fn($service) => $service->where('nama_layanan', 'like', "%{$search}%")));
        }
    }

    private function whereInReport($query, string $column, $value, ?array $allowed = null, ?string $relation = null): void
    {
        $values = collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn($item) => trim((string) $item))->filter()
            ->when($allowed, fn(Collection $items) => $items->filter(fn($item) => in_array($item, $allowed, true)))
            ->values()->all();
        if (!$values) return;
        if ($relation) $query->whereHas($relation, fn($related) => $related->whereIn($column, $values));
        else $query->whereIn($column, $values);
    }

    private function bookingReportSummary($query): array
    {
        $paid = (clone $query)->whereHas('transaksi', fn($transaction) => $transaction
            ->whereIn('status_transaksi', ['Lunas', 'sudah bayar', 'settlement', 'success', 'capture']));
        $failed = (clone $query)->whereHas('transaksi', fn($transaction) => $transaction
            ->whereIn('status_transaksi', ['Gagal', 'gagal', 'Dibatalkan', 'cancel', 'deny', 'expire', 'expired']));
        $paidIds = $paid->select('bookings.id_booking');

        return [
            'total_booking' => (clone $query)->count(),
            'total_berhasil' => (clone $query)->where('status_booking', 'Selesai')->count(),
            'total_gagal' => (clone $query)->where('status_booking', 'Dibatalkan')->count(),
            'total_pending' => (clone $query)->where('status_booking', 'Pending')->count(),
            'total_proses' => (clone $query)->whereIn('status_booking', ['DiPerjalanan', 'Tindakan'])->count(),
            'total_pembayaran_berhasil' => $paid->count(),
            'total_pembayaran_gagal' => $failed->count(),
            'total_pendapatan' => (float) Transaksi::whereIn('id_booking', $paidIds)->sum('jumlah_total'),
            'total_bhp' => (float) Transaksi::whereIn('id_booking', $paidIds)->sum('sb'),
            'total_bhp_tambahan' => (float) Transaksi::whereIn('id_booking', $paidIds)->sum('sb_tambahan'),
        ];
    }

    /**
     * Laporan Kinerja & Hak Keuangan Tenaga Medis (Admin)
     *
     * @queryParam start_date string Tanggal awal filter.
     * @queryParam end_date string Tanggal akhir filter.
     * @queryParam search string Cari nakes berdasarkan nama / email.
     * @queryParam per_page integer|string Jumlah data per halaman.
     */
    public function laporanNakes(Request $request)
    {
        $query = TenagaMedis::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('email', 'like', '%' . $search . '%');
                  })
                  ->orWhere('no_str', 'like', '%' . $search . '%');
            });
        }

        $startDate = $request->start_date;
        $endDate   = $request->end_date;

        $nakesList = $query->get()->map(function ($nakes) use ($startDate, $endDate) {
            $bookingQuery = Booking::where('id_tenaga_medis', $nakes->id_tenaga_medis);

            if ($startDate) {
                $bookingQuery->whereDate('tanggal_kunjungan', '>=', $startDate);
            }
            if ($endDate) {
                $bookingQuery->whereDate('tanggal_kunjungan', '<=', $endDate);
            }

            $totalBooking   = (clone $bookingQuery)->count();
            $totalSelesai   = (clone $bookingQuery)->where('status_booking', 'Selesai')->count();
            $totalDibatalkan = (clone $bookingQuery)->where('status_booking', 'Dibatalkan')->count();

            // Hak nakes dari transaksi booking yang selesai
            $bookingIds = (clone $bookingQuery)->where('status_booking', 'Selesai')->pluck('id_booking');
            $totalHakNakes = Transaksi::whereIn('id_booking', $bookingIds)->sum('hak_nakes');

            return [
                'id_tenaga_medis'    => $nakes->id_tenaga_medis,
                'nama_lengkap'       => $nakes->nama_lengkap,
                'jenis_tenaga_medis' => $nakes->jenis_tenaga_medis,
                'profesi'            => $nakes->jenis_tenaga_medis,
                'no_str'             => $nakes->no_str,
                'nomor_str'          => $nakes->no_str,
                'status'             => $nakes->status,
                'total_booking'      => $totalBooking,
                'total_selesai'      => $totalSelesai,
                'total_dibatalkan'   => $totalDibatalkan,
                'total_hak_nakes'    => (float) $totalHakNakes,
                'rating'             => $nakes->rating ?? 5.0,
            ];
        });

        // Summary Agregat
        $grandTotalNakes       = $nakesList->count();
        $grandTotalCompleted   = $nakesList->sum('total_selesai');
        $grandTotalHakNakes    = $nakesList->sum('total_hak_nakes');
        [$nakesData, $pagination] = $this->paginateCollection($nakesList, $request);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil Laporan Kinerja Nakes',
            'summary' => [
                'total_nakes'          => $grandTotalNakes,
                'total_order_selesai'  => $grandTotalCompleted,
                'total_akumulasi_hak_nakes' => $grandTotalHakNakes,
            ],
            'pagination' => $pagination,
            'data' => $nakesData,
        ], 200);
    }

}
