<?php

namespace App\Http\Controllers;

use App\Models\Artikel;
use App\Models\Booking;
use App\Models\MasterLayanan;
use App\Models\Pasien;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardStatistikController extends Controller
{
    /**
     * Mengambil Ringkasan Statistik Agregat untuk Dashboard Admin.
     *
     * @group CMS Admin
     * @subgroup Dashboard
     */
    public function index(Request $request)
    {
        $now = Carbon::now();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Last month comparison
        $lastMonthDate = Carbon::now()->subMonth();
        $lastMonth = $lastMonthDate->month;
        $lastMonthYear = $lastMonthDate->year;

        // 1. Core Metrics
        $totalPasien = Pasien::count();
        $totalNakesAktif = TenagaMedis::where('status', 'approved')->count();
        $totalNakesPending = TenagaMedis::where('status', 'pending')->count();
        
        $bookingBulanIni = Booking::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        $bookingBulanLalu = Booking::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->count();

        $persenKenaikanBooking = 0;
        if ($bookingBulanLalu > 0) {
            $persenKenaikanBooking = round((($bookingBulanIni - $bookingBulanLalu) / $bookingBulanLalu) * 100, 2);
        } elseif ($bookingBulanIni > 0) {
            $persenKenaikanBooking = 100;
        }

        // Pendapatan Bulan Ini (Lunas)
        $pendapatanBulanIni = (float) Transaksi::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->where(function ($q) {
                $q->where('status_transaksi', 'Lunas')
                  ->orWhere('status_transaksi', 'settlement')
                  ->orWhere('status_transaksi', 'success');
            })
            ->sum('jumlah_total');

        $pendapatanBulanLalu = (float) Transaksi::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->where(function ($q) {
                $q->where('status_transaksi', 'Lunas')
                  ->orWhere('status_transaksi', 'settlement')
                  ->orWhere('status_transaksi', 'success');
            })
            ->sum('jumlah_total');

        $persenKenaikanPendapatan = 0;
        if ($pendapatanBulanLalu > 0) {
            $persenKenaikanPendapatan = round((($pendapatanBulanIni - $pendapatanBulanLalu) / $pendapatanBulanLalu) * 100, 2);
        } elseif ($pendapatanBulanIni > 0) {
            $persenKenaikanPendapatan = 100;
        }

        // 2. Statistik Artikel & Views
        $totalArtikel = Artikel::count();
        $totalViewsArtikel = (int) Artikel::sum('views');
        $topArtikelViewed = Artikel::with('kategori')
            ->orderBy('views', 'desc')
            ->take(5)
            ->get();

        // 3. Distribusi Status Booking
        $distribusiStatusBooking = Booking::select('status_booking', DB::raw('count(*) as total'))
            ->groupBy('status_booking')
            ->get()
            ->pluck('total', 'status_booking');

        // 4. Tren Booking 7 Hari Terakhir
        $trenBooking7Hari = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dateLabel = Carbon::now()->subDays($i)->isoFormat('dddd, D MMM');
            $count = Booking::whereDate('created_at', $date)->count();

            $trenBooking7Hari[] = [
                'tanggal' => $date,
                'label'   => $dateLabel,
                'total'   => $count,
            ];
        }

        // 5. Layanan Paling Populer (Top 5)
        $topLayananPopuler = MasterLayanan::withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data statistik dashboard admin',
            'data'    => [
                'ringkasan' => [
                    'total_pasien'              => $totalPasien,
                    'total_nakes_aktif'         => $totalNakesAktif,
                    'total_nakes_pending'       => $totalNakesPending,
                    'booking_bulan_ini'         => $bookingBulanIni,
                    'persen_kenaikan_booking'   => $persenKenaikanBooking,
                    'pendapatan_bulan_ini'      => $pendapatanBulanIni,
                    'persen_kenaikan_pendapatan'=> $persenKenaikanPendapatan,
                    'total_artikel'             => $totalArtikel,
                    'total_views_artikel'       => $totalViewsArtikel,
                ],
                'top_artikels_by_views'   => $topArtikelViewed,
                'distribusi_status_booking'=> $distribusiStatusBooking,
                'tren_booking_7_hari'     => $trenBooking7Hari,
                'top_layanan_populer'     => $topLayananPopuler,
            ]
        ], 200);
    }
}
