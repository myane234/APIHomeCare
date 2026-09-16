<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_layanan', function (Blueprint $table) {
            $table->unsignedInteger('durasi_menit')->nullable()->after('urutan');
        });
    }

    public function down(): void
    {
        Schema::table('booking_layanan', function (Blueprint $table) {
            $table->dropColumn('durasi_menit');
        });
    }
};
