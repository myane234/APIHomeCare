<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pelayanan</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { background: #0d9488; color: #ffffff; padding: 25px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .content { padding: 30px; }
        .badge-status { display: inline-block; padding: 6px 14px; background: #dcfce7; color: #166534; font-weight: bold; border-radius: 20px; font-size: 13px; text-transform: uppercase; }
        .section-title { font-size: 16px; font-weight: bold; margin-top: 25px; margin-bottom: 12px; border-bottom: 2px solid #f0fdf4; padding-bottom: 6px; color: #0f766e; }
        .info-table, .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        .info-table td.label { width: 40%; color: #64748b; }
        .info-table td.value { width: 60%; font-weight: 500; color: #1e293b; }
        .items-table th, .items-table td { padding: 10px; text-align: left; font-size: 14px; border-bottom: 1px solid #e2e8f0; }
        .items-table th { background: #f8fafc; color: #475569; font-size: 13px; }
        .text-right { text-align: right !important; }
        .total-row td { font-weight: bold; font-size: 15px; border-top: 2px solid #cbd5e1; color: #0f766e; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; }
        .action-button { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #0d9488; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INVOICE & BUKTI PELAYANAN</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">{{ config('app.name', 'HomeCare Service') }}</p>
        </div>

        <div class="content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <span style="color: #64748b; font-size: 13px;">No. Invoice / Booking:</span><br>
                    <strong style="font-size: 18px; color: #0f766e;">{{ $invoice['kode_booking'] ?? ('#' . ($invoice['id_booking'] ?? '-')) }}</strong>
                </div>
                <div class="text-right">
                    <span class="badge-status">{{ $invoice['status_pembayaran'] ?? 'LUNAS' }}</span>
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td class="label">Nama Pasien</td>
                    <td class="value">: {{ $invoice['nama_pasien'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Tenaga Medis (Nakes)</td>
                    <td class="value">: {{ $invoice['nama_nakes'] ?? '-' }} ({{ $invoice['jenis_nakes'] ?? 'Nakes' }})</td>
                </tr>
                <tr>
                    <td class="label">Tanggal Pelayanan</td>
                    <td class="value">: {{ $invoice['tanggal_pelayanan'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Alamat Pelayanan</td>
                    <td class="value">: {{ $invoice['alamat_pelayanan'] ?? '-' }}</td>
                </tr>
                @if(!empty($invoice['metode_pembayaran']))
                <tr>
                    <td class="label">Metode Pembayaran</td>
                    <td class="value">: {{ $invoice['metode_pembayaran'] }}</td>
                </tr>
                @endif
            </table>

            <div class="section-title">Rincian Layanan & Biaya</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Deskripsi Layanan / Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoice['items'] ?? [] as $item)
                        <tr>
                            <td>{{ $item['nama'] }}</td>
                            <td class="text-right">{{ $item['qty'] ?? 1 }}</td>
                            <td class="text-right">Rp {{ number_format($item['harga'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; color: #94a3b8;">Tidak ada item layanan</td>
                        </tr>
                    @endforelse

                    @if(!empty($invoice['biaya_transport']) && $invoice['biaya_transport'] > 0)
                        <tr>
                            <td colspan="2">Biaya Transportasi / Jarak</td>
                            <td class="text-right">Rp {{ number_format($invoice['biaya_transport'], 0, ',', '.') }}</td>
                        </tr>
                    @endif

                    @if(!empty($invoice['diskon_poin']) && $invoice['diskon_poin'] > 0)
                        <tr style="color: #16a34a;">
                            <td colspan="2">Potongan Diskon Poin</td>
                            <td class="text-right">- Rp {{ number_format($invoice['diskon_poin'], 0, ',', '.') }}</td>
                        </tr>
                    @endif

                    <tr class="total-row">
                        <td colspan="2">TOTAL PEMBAYARAN</td>
                        <td class="text-right">Rp {{ number_format($invoice['total_bayar'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            @if(!empty($invoice['catatan_tindakan']))
                <div class="section-title">Catatan Tindakan Medis</div>
                <p style="background: #f8fafc; padding: 12px; border-radius: 6px; font-size: 13px; line-height: 1.5; color: #334155;">
                    {{ $invoice['catatan_tindakan'] }}
                </p>
            @endif

            <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 25px;">
                Terima kasih atas kepercayaan Anda menggunakan layanan kami. Jika Anda memiliki pertanyaan atau kendala, silakan hubungi tim layanan pelanggan kami.
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'HomeCare') }}. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
