<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BookingResource
 *
 * Menyajikan data booking secara terstruktur dan konsisten di seluruh API.
 * Mencakup: kode booking, status realtime, info pasien, layanan, nakes, dan laporan transaksi.
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $transaksi = $this->whenLoaded('transaksi');

        return [
            // ─── Identitas Booking ───────────────────────────────────────
            'id_booking'        => $this->id_booking,
            'booking_code'      => $this->booking_code,
            'medical_record_number' => $this->medical_record_number,
            'status_booking'    => $this->status_booking,
            'status_label'      => $this->statusLabel(),
            'status_color'      => $this->statusColor(),

            // ─── Waktu & Lokasi ──────────────────────────────────────────
            'tanggal_kunjungan' => $this->tanggal_kunjungan
                ? \Carbon\Carbon::parse($this->tanggal_kunjungan)->translatedFormat('l, d F Y')
                : null,
            'tanggal_kunjungan_raw' => $this->tanggal_kunjungan,
            'jam_kunjungan'     => $this->jam_kunjungan,
            'alamat_kunjungan'  => $this->alamat_kunjungan,
            'koordinat_kunjungan' => [
                'latitude'  => (float) $this->latitude_kunjungan,
                'longitude' => (float) $this->longitude_kunjungan,
            ],

            // ─── Tanggal Dibuat / Diperbarui ────────────────────────────
            'dibuat_pada'       => $this->created_at
                ? \Carbon\Carbon::parse($this->created_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                : null,
            'diperbarui_pada'   => $this->updated_at
                ? \Carbon\Carbon::parse($this->updated_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                : null,
            'created_at_raw'    => $this->created_at,
            'updated_at_raw'    => $this->updated_at,

            // ─── Pasien ──────────────────────────────────────────────────
            'pasien'            => $this->when($this->relationLoaded('pasien') && $this->pasien, [
                'id_pasien'     => $this->pasien?->id_pasien,
                'nama_lengkap'  => $this->pasien?->nama_lengkap,
                'no_telp'       => $this->pasien?->no_telp,
                'alamat_utama'  => $this->pasien?->alamat_utama,
            ]),

            // ─── Layanan Utama (backward compat, hanya jika layananItems tidak ada/kosong) ───
            'layanan'           => $this->when(
                $this->relationLoaded('layanan') && $this->layanan && (!$this->relationLoaded('layananItems') || $this->layananItems->isEmpty()),
                [
                    'id_layanan'    => $this->layanan?->id_layanan,
                    'nama_layanan'  => $this->layanan?->nama_layanan,
                    'tipe_layanan'  => $this->layanan?->tipe_layanan,
                    'foto_layanan'  => $this->layanan?->foto_layanan,
                ]
            ),

            // ─── Semua Layanan dalam Booking (multi-layanan) ─────────────
            'layanan_items'     => $this->when(
                $this->relationLoaded('layananItems') && $this->layananItems->isNotEmpty(),
                fn() => $this->layananItems->map(fn($item) => [
                    'id_layanan'        => $item->id_layanan,
                    'nama_layanan'      => $item->layanan?->nama_layanan,
                    'tipe_layanan'      => $item->layanan?->tipe_layanan,
                    'foto_layanan'      => $item->layanan?->foto_layanan,
                    'urutan'            => $item->urutan,
                    'sl'                => (float) $item->sl,
                    'sb'                => (float) $item->sb,
                    'hak_nakes_layanan' => (float) $item->hak_nakes_layanan,
                ])->values()
            ),

            // ─── Tenaga Medis ────────────────────────────────────────────
            'tenaga_medis'      => $this->when($this->relationLoaded('tenagaMedis'), function () {
                $nakes = $this->tenagaMedis;
                if (!$nakes) return null;
                return [
                    'id_tenaga_medis'   => $nakes->id_tenaga_medis,
                    'nama_lengkap'      => $nakes->nama_lengkap,
                    'nama_panggilan'    => $nakes->nama_panggilan,
                    'jenis_tenaga_medis'=> $nakes->jenis_tenaga_medis,
                    'foto_profile'      => $nakes->foto_profile,
                    'no_telp'           => $nakes->no_telp,
                ];
            }),

            // ─── Laporan Transaksi ───────────────────────────────────────
            'transaksi'         => $this->when($this->relationLoaded('transaksi') && $transaksi, function () use ($transaksi) {
                return [
                    'id_transaksi'      => $transaksi->id_transaksi,
                    'status_transaksi'  => $transaksi->status_transaksi,
                    'metode_pembayaran' => $transaksi->metode_pembayaran,
                    'waktu_bayar'       => $transaksi->waktu_bayar
                        ? \Carbon\Carbon::parse($transaksi->waktu_bayar)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
                        : null,
                    'rincian_biaya'     => [
                        'sl'          => (float) $transaksi->sl,
                        'sb'          => (float) $transaksi->sb,
                        'sb_tambahan' => (float) ($transaksi->sb_tambahan ?? 0),
                        'st'          => (float) $transaksi->st,
                        'ba'          => (float) $transaksi->ba,
                        'ppn'         => (float) $transaksi->ppn,
                    ],
                    'persentase'        => [
                        'ppn'       => (float) $transaksi->persen_ppn,
                        'fee_nakes' => (float) $transaksi->persen_fee_nakes,
                    ],
                    'bagi_hasil'        => [
                        'hak_nakes'        => (float) $transaksi->hak_nakes,
                        'profit_hc'        => (float) $transaksi->profit_hc,
                        'fee_midtrans'     => (float) $transaksi->fee_midtrans,
                        'hpp_bhp'          => (float) $transaksi->hpp_bhp,
                        'hpp_bhp_tambahan' => (float) ($transaksi->hpp_bhp_tambahan ?? 0),
                    ],
                    'jumlah_total'      => (float) $transaksi->jumlah_total,
                    'jumlah_total_format' => 'Rp ' . number_format((float) $transaksi->jumlah_total, 0, ',', '.'),
                ];
            }),

            // ─── Detail BHP Tambahan (saat/setelah tindakan) ─────────────
            'booking_bhp'       => $this->when($this->relationLoaded('bookingBhp'), fn() => $this->bookingBhp->map(fn($item) => [
                'id_booking_bhp'     => $item->id_booking_bhp,
                'id_layanan'         => $item->id_layanan,
                'id_bhp'             => $item->id_bhp,
                'nama_bhp'           => $item->bhpItem?->nama_bhp,
                'qty_default'        => (int) $item->qty_default,
                'qty_real'           => (int) $item->qty_real,
                'qty_tambahan'       => (int) $item->qty_tambahan,
                'harga_jual'         => (float) $item->harga_jual,
                'total_sb_tambahan'  => (float) $item->total_sb_tambahan,
            ])->values()),
        ];
    }

    // ─── Helper: label status Indonesia ──────────────────────────────────────
    private function statusLabel(): string
    {
        return match($this->status_booking) {
            'Pending'     => 'Menunggu Konfirmasi',
            'DiPerjalanan'=> 'Tenaga Medis Dalam Perjalanan',
            'Tindakan'    => 'Sedang Dalam Tindakan',
            'Selesai'     => 'Selesai',
            'Dibatalkan'  => 'Dibatalkan',
            default       => $this->status_booking ?? '-',
        };
    }

    // ─── Helper: warna status untuk frontend ─────────────────────────────────
    private function statusColor(): string
    {
        return match($this->status_booking) {
            'Pending'     => 'orange',
            'DiPerjalanan'=> 'blue',
            'Tindakan'    => 'purple',
            'Selesai'     => 'green',
            'Dibatalkan'  => 'red',
            default       => 'gray',
        };
    }
}
