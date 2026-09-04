<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaksiDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;
        $pasien = $booking->pasien;
        $layanan = $booking->layanan;
        $layananItems = $booking->relationLoaded('layananItems')
            ? $booking->layananItems
            : collect();
        $nakes = $booking->tenagaMedis;
        $transaksi = $booking->transaksi;

        return [
            'booking' => [
                'id_booking' => $booking->id_booking,
                'booking_code' => $booking->booking_code,
                'medical_record_number' => $booking->medical_record_number,
                'status' => [
                    'value' => $booking->status_booking,
                    'label' => $this->statusLabel($booking->status_booking),
                ],
                'catatan_penolakan' => $booking->catatan_penolakan,
                'dibuat_pada' => $this->dateTime($booking->created_at),
                'diperbarui_pada' => $this->dateTime($booking->updated_at),
            ],
            'pasien' => $pasien ? [
                'id_pasien' => $pasien->id_pasien,
                'nama_lengkap' => $pasien->nama_lengkap,
                'no_hp' => $pasien->no_hp ?? $pasien->no_telp,
                'jenis_kelamin' => $pasien->jenis_kelamin,
                'golongan_darah' => $pasien->golongan_darah,
                'alamat_utama' => $pasien->alamat_utama,
                'avatar' => $pasien->avatar,
            ] : null,
            'kunjungan' => [
                'tanggal' => $booking->tanggal_kunjungan,
                'tanggal_format' => $booking->tanggal_kunjungan
                    ? \Carbon\Carbon::parse($booking->tanggal_kunjungan)->translatedFormat('l, d F Y')
                    : null,
                'jam' => $booking->jam_kunjungan,
                'alamat' => $booking->alamat_kunjungan,
                'koordinat' => [
                    'latitude' => $booking->latitude_kunjungan !== null ? (float) $booking->latitude_kunjungan : null,
                    'longitude' => $booking->longitude_kunjungan !== null ? (float) $booking->longitude_kunjungan : null,
                ],
            ],
            'layanan' => ($layanan && $layananItems->isEmpty()) ? [
                'id_layanan' => $layanan->id_layanan,
                'nama_layanan' => $layanan->nama_layanan,
                'deskripsi' => $layanan->deskripsi_layanan,
                'tipe' => $layanan->tipe_layanan,
                'durasi_menit' => $layanan->durasi_menit,
                'harga' => (float) $layanan->harga,
                'harga_format' => $this->money($layanan->harga),
                'foto' => $layanan->foto_layanan,
                'kategori' => $layanan->kategori ? [
                    'id_kategori_layanan' => $layanan->kategori->id_kategori_layanan,
                    'nama' => $layanan->kategori->nama_kategori,
                ] : null,
                'bhp' => $layanan->bhpItems->map(fn ($item) => [
                    'id_bhp' => $item->id_bhp,
                    'nama' => $item->nama_bhp ?? $item->nama,
                    'jumlah' => (int) ($item->pivot->qty_default ?? 1),
                    'wajib' => (bool) ($item->pivot->is_mandatory ?? false),
                    'harga_satuan' => (float) $item->harga_jual,
                    'harga_total' => (float) $item->harga_jual * (int) ($item->pivot->qty_default ?? 1),
                ])->values(),
            ] : null,
            'layanan_items' => $layananItems->map(fn ($item) => [
                'id_layanan' => $item->id_layanan,
                'nama_layanan' => $item->layanan?->nama_layanan,
                'deskripsi' => $item->layanan?->deskripsi_layanan,
                'tipe' => $item->layanan?->tipe_layanan,
                'durasi_menit' => $item->layanan?->durasi_menit,
                'harga' => $item->layanan ? (float) $item->layanan->harga : null,
                'harga_format' => $item->layanan ? $this->money($item->layanan->harga) : null,
                'foto' => $item->layanan?->foto_layanan,
                'urutan' => $item->urutan,
                'rincian_booking' => [
                    'sl' => $this->moneyData($item->sl),
                    'bhp' => $this->moneyData($item->sb),
                    'hpp_bhp' => $this->moneyData($item->hpp_bhp),
                    'hak_nakes' => $this->moneyData($item->hak_nakes_layanan),
                ],
            ])->values(),
            'tenaga_medis' => $nakes ? [
                'id_tenaga_medis' => $nakes->id_tenaga_medis,
                'nama_lengkap' => $nakes->nama_lengkap,
                'nama_panggilan' => $nakes->nama_panggilan,
                'jenis_tenaga_medis' => $nakes->jenis_tenaga_medis,
                'jenis_kelamin' => $nakes->jenis_kelamin,
                'tempat_lahir' => $nakes->tempat_lahir,
                'tanggal_lahir' => $nakes->tanggal_lahir?->format('Y-m-d'),
                'agama' => $nakes->agama,
                'no_telp' => $nakes->no_telp,
                'alamat_lengkap' => $nakes->alamat_lengkap,
                'foto_profile' => $nakes->foto_profile,
                'tempat_kerja' => $nakes->tempat_kerja,
                'lama_bekerja' => $nakes->lama_bekerja,
                'status' => $nakes->status,
            ] : null,
            'transaksi' => $transaksi ? [
                'id_transaksi' => $transaksi->id_transaksi,
                'status' => $transaksi->status_transaksi,
                'metode_pembayaran' => $transaksi->metode_pembayaran,
                'payment_method' => $transaksi->payment_method,
                'midtrans_transaction_id' => $transaksi->midtrans_transaction_id,
                'midtrans_order_id' => $transaksi->midtrans_order_id,
                'va_number' => $transaksi->va_number,
                'bank_va' => $transaksi->bank_va,
                'qr_string' => $transaksi->qr_string,
                'qr_url' => $transaksi->qr_url,
                'waktu_bayar' => $this->dateTime($transaksi->waktu_bayar),
                'rincian_biaya' => [
                    'layanan' => $this->moneyData($transaksi->sl),
                    'bhp' => $this->moneyData($transaksi->sb),
                    'bhp_tambahan' => $this->moneyData($transaksi->sb_tambahan ?? 0),
                    'transportasi' => $this->moneyData($transaksi->st),
                    'administrasi' => $this->moneyData($transaksi->ba),
                    'ppn' => $this->moneyData($transaksi->ppn),
                    'persentase_ppn' => (float) $transaksi->persen_ppn,
                    'total' => $this->moneyData($transaksi->jumlah_total),
                ],
            ] : null,
        ];
    }

    private function moneyData($value): array
    {
        return ['nilai' => (float) $value, 'format' => $this->money($value)];
    }

    private function money($value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    private function dateTime($value): ?string
    {
        return $value
            ? \Carbon\Carbon::parse($value)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB'
            : null;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'Pending' => 'Menunggu Konfirmasi',
            'DiPerjalanan' => 'Tenaga Medis Dalam Perjalanan',
            'Tindakan' => 'Sedang Dalam Tindakan',
            'Selesai' => 'Selesai',
            'Dibatalkan' => 'Dibatalkan',
            default => $status ?? '-',
        };
    }
}