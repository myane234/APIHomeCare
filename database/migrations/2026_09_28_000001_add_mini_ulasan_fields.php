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
        // 1. Kolom mini_ulasan_config pada content_managements
        Schema::table('content_managements', function (Blueprint $table) {
            if (!Schema::hasColumn('content_managements', 'mini_ulasan_config')) {
                $table->json('mini_ulasan_config')->nullable()->after('ulasan_subheading');
            }
        });

        // 2. Kolom id_booking, id_tenaga_medis, quick_tags pada ulasans
        Schema::table('ulasans', function (Blueprint $table) {
            if (!Schema::hasColumn('ulasans', 'id_booking')) {
                $table->unsignedBigInteger('id_booking')->nullable()->after('id_user');
                $table->foreign('id_booking')
                      ->references('id_booking')
                      ->on('bookings')
                      ->onDelete('set null');
            }

            if (!Schema::hasColumn('ulasans', 'id_tenaga_medis')) {
                $table->unsignedBigInteger('id_tenaga_medis')->nullable()->after('id_booking');
                $table->foreign('id_tenaga_medis')
                      ->references('id_tenaga_medis')
                      ->on('tenaga_medis')
                      ->onDelete('set null');
            }

            if (!Schema::hasColumn('ulasans', 'quick_tags')) {
                $table->json('quick_tags')->nullable()->after('komentar');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_managements', function (Blueprint $table) {
            if (Schema::hasColumn('content_managements', 'mini_ulasan_config')) {
                $table->dropColumn('mini_ulasan_config');
            }
        });

        Schema::table('ulasans', function (Blueprint $table) {
            if (Schema::hasColumn('ulasans', 'id_booking')) {
                $table->dropForeign(['id_booking']);
                $table->dropColumn('id_booking');
            }

            if (Schema::hasColumn('ulasans', 'id_tenaga_medis')) {
                $table->dropForeign(['id_tenaga_medis']);
                $table->dropColumn('id_tenaga_medis');
            }

            if (Schema::hasColumn('ulasans', 'quick_tags')) {
                $table->dropColumn('quick_tags');
            }
        });
    }
};
