<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_kategori_tarif', function (Blueprint $table) {
            $table->decimal('biaya_tambahan', 12, 2)->default(0)->after('nama_kategori');
        });
    }

    public function down(): void
    {
        Schema::table('master_kategori_tarif', function (Blueprint $table) {
            $table->dropColumn('biaya_tambahan');
        });
    }
};
