<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom broadcast_tier dan fraud_at ke tabel bookings.
     *
     * broadcast_tier : Tier broadcast saat ini (1–4). Tier 1 = 0-10km, Tier 2 = 10-20km, dst.
     *                  Null berarti belum ada nakes yang di-broadcast.
     * fraud_at       : Timestamp ketika booking dinyatakan fraud (semua nakes di semua tier menolak).
     *                  Null berarti booking normal.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('broadcast_tier')
                ->nullable()
                ->default(null)
                ->after('status_booking')
                ->comment('Tier broadcast aktif saat ini: 1=0-10km, 2=10-20km, 3=20-30km, 4=30-40km');

            $table->timestamp('fraud_at')
                ->nullable()
                ->default(null)
                ->after('broadcast_tier')
                ->comment('Timestamp booking dinyatakan fraud karena semua nakes menolak');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['broadcast_tier', 'fraud_at']);
        });
    }
};
