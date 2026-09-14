<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Transaksi;
use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $perPage = $request->input('per_page', 15);
        if ($perPage === 'all') {
            $items = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 15;
            $items = $query->paginate($perPage);
        }

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
            'data' => $items,
        ], 200);
    }

    /**
     * Laporan Rekapitulasi Booking / Pelayanan (Admin)
     *
     * @queryParam start_date string Tanggal awal filter (YYYY-MM-DD).
     * @queryParam end_date string Tanggal akhir filter (YYYY-MM-DD).
     * @queryParam status_booking string Status booking (Pending, DiPerjalanan, Tindakan, Selesai, Dibatalkan).
     * @queryParam id_layanan integer Filter berdasarkan id layanan.
     * @queryParam per_page integer|string Jumlah data per halaman.
     */
    public function laporanBooking(Request $request)
    {
        $query = Booking::with(['pasien', 'layanan', 'tenagaMedis', 'transaksi']);

        if ($request->filled('start_date')) {
            $query->whereDate('tanggal_kunjungan', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('tanggal_kunjungan', '<=', $request->end_date);
        }
        if ($request->filled('status_booking')) {
            $query->where('status_booking', $request->status_booking);
        }
        if ($request->filled('id_layanan')) {
            $query->where('id_layanan', $request->id_layanan);
        }

        // Summary Agregat Status
        $summaryQuery   = clone $query;
        $totalBooking   = $summaryQuery->count();
        $totalSelesai   = (clone $query)->where('status_booking', 'Selesai')->count();
        $totalDibatalkan = (clone $query)->where('status_booking', 'Dibatalkan')->count();
        $totalPending   = (clone $query)->where('status_booking', 'Pending')->count();
        $totalProses    = (clone $query)->whereIn('status_booking', ['DiPerjalanan', 'Tindakan'])->count();

        // Breakdown per Layanan
        $breakdownLayanan = (clone $query)
            ->select('id_layanan', DB::raw('count(*) as total'))
            ->groupBy('id_layanan')
            ->with('layanan:id_layanan,nama_layanan')
            ->get()
            ->map(function ($item) {
                return [
                    'id_layanan'   => $item->id_layanan,
                    'nama_layanan' => $item->layanan ? $item->layanan->nama_layanan : 'Layanan tidak ditemukan',
                    'total'        => $item->total,
                ];
            });

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 15);
        if ($perPage === 'all') {
            $items = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 15;
            $items = $query->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil Laporan Booking',
            'summary' => [
                'total_booking'    => $totalBooking,
                'total_selesai'    => $totalSelesai,
                'total_dibatalkan' => $totalDibatalkan,
                'total_pending'    => $totalPending,
                'total_proses'     => $totalProses,
                'breakdown_layanan' => $breakdownLayanan,
            ],
            'data' => $items,
        ], 200);
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

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil Laporan Kinerja Nakes',
            'summary' => [
                'total_nakes'          => $grandTotalNakes,
                'total_order_selesai'  => $grandTotalCompleted,
                'total_akumulasi_hak_nakes' => $grandTotalHakNakes,
            ],
            'data' => $nakesList,
        ], 200);
    }
}
