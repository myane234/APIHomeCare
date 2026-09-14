<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('master_kategori_tarif')
            ->where(function ($query) {
                $query->whereRaw('LOWER(nama_kategori) LIKE ?', ['%weekend%'])
                    ->orWhereRaw('LOWER(nama_kategori) LIKE ?', ['%akhir pekan%']);
            })
            ->update([
                'hari_berlaku' => json_encode(['sabtu', 'minggu']),
                'jam_mulai' => '06:00:00',
                'jam_selesai' => '22:00:00',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('master_kategori_tarif')
            ->where(function ($query) {
                $query->whereRaw('LOWER(nama_kategori) LIKE ?', ['%weekend%'])
                    ->orWhereRaw('LOWER(nama_kategori) LIKE ?', ['%akhir pekan%']);
            })
            ->update([
                'jam_mulai' => '00:00:00',
                'jam_selesai' => '23:59:00',
                'updated_at' => now(),
            ]);
    }
};