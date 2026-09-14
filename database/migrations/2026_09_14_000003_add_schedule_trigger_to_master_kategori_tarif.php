<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_kategori_tarif', function (Blueprint $table) {
            $table->json('hari_berlaku')->nullable()->after('is_default');
            $table->time('jam_mulai')->nullable()->after('hari_berlaku');
            $table->time('jam_selesai')->nullable()->after('jam_mulai');
        });

        DB::table('master_kategori_tarif')->update(['is_default' => false]);

        DB::table('master_kategori_tarif')
            ->whereRaw('LOWER(nama_kategori) = ?', ['reguler'])
            ->update(['is_default' => true, 'hari_berlaku' => null, 'jam_mulai' => null, 'jam_selesai' => null]);

        DB::table('master_kategori_tarif')
            ->where(function ($query) {
                $query->whereRaw('LOWER(nama_kategori) LIKE ?', ['%malam%']);
            })
            ->update([
                'is_default' => false,
                'hari_berlaku' => json_encode(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']),
                'jam_mulai' => '22:00:00',
                'jam_selesai' => '06:00:00',
            ]);

        DB::table('master_kategori_tarif')
            ->where(function ($query) {
                $query->whereRaw('LOWER(nama_kategori) LIKE ?', ['%weekend%'])
                    ->orWhereRaw('LOWER(nama_kategori) LIKE ?', ['%akhir pekan%']);
            })
            ->update([
                'is_default' => false,
                'hari_berlaku' => json_encode(['sabtu', 'minggu']),
                'jam_mulai' => '00:00:00',
                'jam_selesai' => '23:59:00',
            ]);
    }

    public function down(): void
    {
        Schema::table('master_kategori_tarif', function (Blueprint $table) {
            $table->dropColumn(['hari_berlaku', 'jam_mulai', 'jam_selesai']);
        });
    }
};