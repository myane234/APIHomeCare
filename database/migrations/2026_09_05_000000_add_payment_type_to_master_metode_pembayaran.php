<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_metode_pembayaran', function (Blueprint $table) {
            $table->string('payment_type', 50)->nullable()->unique()->after('id_kategori_pembayaran');
        });
    }

    public function down(): void
    {
        Schema::table('master_metode_pembayaran', function (Blueprint $table) {
            $table->dropUnique(['payment_type']);
            $table->dropColumn('payment_type');
        });
    }
};