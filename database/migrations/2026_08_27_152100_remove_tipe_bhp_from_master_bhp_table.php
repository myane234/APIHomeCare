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
        Schema::table('master_bhp', function (Blueprint $table) {
            $table->dropColumn('tipe_bhp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_bhp', function (Blueprint $table) {
            $table->enum('tipe_bhp', ['satuan', 'paket'])->default('satuan');
        });
    }
};
