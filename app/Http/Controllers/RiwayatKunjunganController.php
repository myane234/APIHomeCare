<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\RiwayatKunjungan;
use App\Models\TenagaMedis;
use Illuminate\Http\Request;

class RiwayatKunjunganController extends Controller
{
    /**
     * Membuat atau memperbarui riwayat kunjungan untuk nakes yang menerima booking.
     */
    public function syncFromBooking(Booking $booking): RiwayatKunjungan
    {
        if (!$booking->id_tenaga_medis) {
            throw new \InvalidArgumentException('Booking belum memiliki tenaga medis.');
        }

        $status = $booking->status_booking;
        if (!in_array($status, ['DiPerjalanan', 'Tindakan', 'Selesai'], true)) {
            throw new \InvalidArgumentException('Status booking tidak dapat dicatat sebagai riwayat kunjungan.');
        }

        return RiwayatKunjungan::updateOrCreate(
            [
                'id_booking' => $booking->id_booking,
                'id_tenaga_medis' => $booking->id_tenaga_medis,
            ],
            ['status_kunjungan' => $status]
        );
    }

    /**
     * API Nakes: daftar riwayat kunjungan milik nakes yang sedang login.
     */
    public function index(Request $request)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.',
                'data' => [],
            ], 404);
        }

        $request->validate([
            'status' => 'nullable|in:DiPerjalanan,Tindakan,Selesai',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = RiwayatKunjungan::with([
            'booking.pasien',
            'booking.layanan',
            'booking.layananItems.layanan',
            'booking.transaksi',
        ])
            ->where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->when($request->filled('status'), fn ($query) => $query->where('status_kunjungan', $request->input('status')))
            ->latest('updated_at');

        $riwayat = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat kunjungan Tenaga Medis',
            'pagination' => [
                'total' => $riwayat->total(),
                'count' => $riwayat->count(),
                'per_page' => $riwayat->perPage(),
                'current_page' => $riwayat->currentPage(),
                'total_pages' => $riwayat->lastPage(),
                'has_more_pages' => $riwayat->hasMorePages(),
            ],
            'data' => $riwayat->getCollection()->map(fn (RiwayatKunjungan $item) => $this->formatHistory($item)),
        ]);
    }

    /**
     * API Nakes: detail satu riwayat kunjungan.
     */
    public function show(Request $request, $id)
    {
        $nakes = $this->getLoggedNakes($request);

        if (!$nakes) {
            return response()->json([
                'success' => false,
                'message' => 'Profil Tenaga Medis tidak ditemukan.',
            ], 404);
        }

        $riwayat = RiwayatKunjungan::with([
            'booking.pasien',
            'booking.layanan',
            'booking.layananItems.layanan',
            'booking.transaksi',
        ])
            ->where('id_tenaga_medis', $nakes->id_tenaga_medis)
            ->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Riwayat kunjungan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail riwayat kunjungan',
            'data' => $this->formatHistory($riwayat),
        ]);
    }

    private function getLoggedNakes(Request $request): ?TenagaMedis
    {
        $user = $request->user();

        return $user
            ? TenagaMedis::where('id_user', $user->id_user ?? $user->id)
                ->where('status', 'approved')
                ->first()
            : null;
    }

    private function formatHistory(RiwayatKunjungan $riwayat): array
    {
        return [
            'id_riwayat_kunjungan' => $riwayat->id_riwayat_kunjungan,
            'id_booking' => $riwayat->id_booking,
            'id_tenaga_medis' => $riwayat->id_tenaga_medis,
            'status_kunjungan' => $riwayat->status_kunjungan,
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at,
            'booking' => $riwayat->booking ? (new BookingResource($riwayat->booking))->resolve() : null,
        ];
    }
}
