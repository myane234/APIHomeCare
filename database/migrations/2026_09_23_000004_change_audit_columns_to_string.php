<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Semua tabel yang memiliki kolom audit.
     */
    private array $tables = [
        'activity_logs',
        'admins',
        'admin_tiers',
        'artikels',
        'bookings',
        'content_managements',
        'global_configs',
        'jadwal_kerjas',
        'kategori_artikels',
        'kategori_layanans',
        'legalities',
        'master_agama',
        'master_bank',
        'master_bhp',
        'master_kategori_pembayaran',
        'master_kategori_tarif',
        'master_kecamatan',
        'master_kelurahan',
        'master_komponen_biaya',
        'master_kota_kabupaten',
        'master_layanan',
        'master_metode_pembayaran',
        'master_pendidikan',
        'master_provinsi',
        'master_tarif',
        'master_tarif_transport',
        'master_universitas',
        'notification_templates',
        'operasional_nakes',
        'pasiens',
        'pesan_kontaks',
        'promos',
        'riwayat_kunjungan',
        'seo_configs',
        'tags',
        'tenaga_medis',
        'transaksis',
        'transaksi_tambahan',
        'ulasans',
        'users',
        'wilayah_layanan',
        'point_settings',
        'booking_bhp',
        'booking_layanan',
    ];

    private array $auditColumns = ['created_by', 'updated_by', 'deleted_by'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ($this->auditColumns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->string($column, 100)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ($this->auditColumns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->unsignedBigInteger($column)->nullable()->change();
                    }
                }
            });
        }
    }
};
