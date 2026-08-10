<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tambahkan kolom-kolom snapshot ke tabel transaksis.
     *
     * Kolom-kolom ini menyimpan "foto" dari perhitungan biaya
     * pada saat booking dibuat — agar histori tarif tetap akurat
     * meskipun template master_tarif diubah di kemudian hari.
     *
     *  sl              : Tarif Layanan (Jasa)
     *  sb              : Tarif BHP (Bahan Habis Pakai)
     *  st              : Tarif Transport
     *  ba              : Biaya Administrasi (snapshot dari master_tarif.biaya_admin)
     *  ppn             : Nominal PPN yang dikenakan
     *  persen_ppn      : Persentase PPN saat transaksi (snapshot)
     *  persen_fee_nakes: Persentase fee nakes saat transaksi (snapshot)
     *  fee_midtrans    : Biaya payment gateway (Midtrans)
     *  hpp_bhp         : Harga Pokok Penjualan BHP
     *  hak_nakes       : Nominal yang menjadi hak nakes
     *  profit_hc       : Estimasi profit HomeCare dari transaksi ini
     */
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Komponen biaya (snapshot)
            $table->decimal('sl', 12, 2)->default(0)->after('jumlah_total')->comment('Tarif Layanan / Jasa');
            $table->decimal('sb', 12, 2)->default(0)->after('sl')->comment('Tarif BHP');
            $table->decimal('st', 12, 2)->default(0)->after('sb')->comment('Tarif Transport');
            $table->decimal('ba', 12, 2)->default(0)->after('st')->comment('Biaya Administrasi');
            $table->decimal('ppn', 12, 2)->default(0)->after('ba')->comment('Nominal PPN');

            // Snapshot persentase dari master_tarif
            $table->decimal('persen_ppn', 5, 2)->default(0)->after('ppn')->comment('% PPN saat transaksi');
            $table->decimal('persen_fee_nakes', 5, 2)->default(0)->after('persen_ppn')->comment('% fee nakes saat transaksi');

            // Biaya lain
            $table->decimal('fee_midtrans', 12, 2)->default(0)->after('persen_fee_nakes')->comment('Biaya payment gateway');
            $table->decimal('hpp_bhp', 12, 2)->default(0)->after('fee_midtrans')->comment('HPP BHP');

            // Bagi hasil
            $table->decimal('hak_nakes', 12, 2)->default(0)->after('hpp_bhp')->comment('Nominal hak nakes');
            $table->decimal('profit_hc', 12, 2)->default(0)->after('hak_nakes')->comment('Estimasi profit HomeCare');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn([
                'sl',
                'sb',
                'st',
                'ba',
                'ppn',
                'persen_ppn',
                'persen_fee_nakes',
                'fee_midtrans',
                'hpp_bhp',
                'hak_nakes',
                'profit_hc',
            ]);
        });
    }
};
