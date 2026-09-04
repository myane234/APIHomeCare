<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->decimal('sb_tambahan', 12, 2)->default(0)->after('sb')->comment('Tarif SB/BHP Tambahan saat Tindakan');
            $table->decimal('hpp_bhp_tambahan', 12, 2)->default(0)->after('hpp_bhp')->comment('HPP BHP Tambahan saat Tindakan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn(['sb_tambahan', 'hpp_bhp_tambahan']);
        });
    }
};
