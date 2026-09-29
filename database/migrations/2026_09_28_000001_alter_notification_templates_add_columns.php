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
        Schema::table('notification_templates', function (Blueprint $table) {
            // Tambah kolom baru setelah kolom 'code'
            $table->string('target_role')->nullable()->after('code')
                ->comment('Target role penerima: pasien, nakes, admin, all');
            $table->string('trigger_type')->nullable()->after('target_role')
                ->comment('Pemicu notifikasi: manual, booking_created, booking_selesai, transaksi_paid, dll');
            $table->string('action_url')->nullable()->after('body')
                ->comment('URL aksi deep-link pada notifikasi');

            // Soft deletes + audit columns sudah di-handle oleh AuditableSoftDeletes
            // tapi migration lama belum include, tambahkan jika belum ada
            if (!Schema::hasColumn('notification_templates', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn(['target_role', 'trigger_type', 'action_url']);
        });
    }
};
