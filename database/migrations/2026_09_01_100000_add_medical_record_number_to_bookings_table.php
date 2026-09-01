<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('medical_record_number')->nullable()->after('booking_code')->unique();
            $table->json('catatan_penolakan')->nullable()->after('status_booking');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('medical_record_number');
            $table->dropColumn('catatan_penolakan');
        });
    }
};
