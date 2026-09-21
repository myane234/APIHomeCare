<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'activity_logs', 'admins', 'admin_tiers', 'artikels', 'master_bhp', 'bookings', 'kategori_artikels',
        'kategori_layanans', 'master_kategori_pembayaran', 'master_komponen_biaya',
        'master_kecamatan', 'master_kelurahan', 'master_kota_kabupaten', 'master_provinsi',
        'master_bhp', 'master_layanan', 'master_tarif', 'master_tarif_transport',
        'master_agama', 'master_bank', 'master_kategori_tarif', 'master_pendidikan',
        'master_universitas', 'master_metode_pembayaran', 'notification_templates',
        'operasional_nakes', 'pasiens', 'promos', 'tags', 'tenaga_medis', 'ulasans',
        'users', 'wilayah_layanan', 'transaksis', 'transaksi_tambahan', 'pesan_kontak',
        'legalities', 'content_managements', 'global_configs', 'jadwal_kerjas',
        'riwayat_kunjungan', 'pesan_kontaks', 'seo_configs', 'tarif_transport', 'master_provinsi',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (!Schema::hasColumn($tableName, 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletes();
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
                $columns = array_values(array_filter([
                    Schema::hasColumn($tableName, 'created_by') ? 'created_by' : null,
                    Schema::hasColumn($tableName, 'updated_by') ? 'updated_by' : null,
                    Schema::hasColumn($tableName, 'deleted_by') ? 'deleted_by' : null,
                ]));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
                if (Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
