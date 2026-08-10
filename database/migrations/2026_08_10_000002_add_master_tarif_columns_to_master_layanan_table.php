<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_layanan', function (Blueprint $table) {
            // Harga dasar layanan (SL)
            $table->decimal('harga', 12, 2)
                ->default(0)
                ->after('deskripsi_layanan')
                ->comment('Harga jasa layanan (SL) sebelum komponen biaya lain');

            // Flag transport
            $table->boolean('include_transport')
                ->default(false)
                ->after('harga')
                ->comment('True = transport sudah termasuk di harga, tidak dihitung terpisah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_layanan', function (Blueprint $table) {
            $table->dropColumn(['harga', 'include_transport']);
        });
    }
};