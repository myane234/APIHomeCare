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
        Schema::table('content_managements', function (Blueprint $table) {
            // Header Ulasan
            $table->string('ulasan_heading')->nullable();
            $table->text('ulasan_subheading')->nullable();

            // Hubungi Kami Content Settings
            $table->string('hubungi_banner')->nullable();
            $table->string('hubungi_banner_text')->nullable();
            $table->string('hubungi_heading')->nullable();
            $table->text('hubungi_description')->nullable();
            $table->string('hubungi_phone')->nullable();
            $table->string('hubungi_email')->nullable();
            $table->string('hubungi_whatsapp')->nullable();
            $table->text('hubungi_address')->nullable();
            $table->text('hubungi_maps_link')->nullable();
            $table->text('hubungi_jam_operasional')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_managements', function (Blueprint $table) {
            $table->dropColumn([
                'ulasan_heading',
                'ulasan_subheading',
                'hubungi_banner',
                'hubungi_banner_text',
                'hubungi_heading',
                'hubungi_description',
                'hubungi_phone',
                'hubungi_email',
                'hubungi_whatsapp',
                'hubungi_address',
                'hubungi_maps_link',
                'hubungi_jam_operasional',
            ]);
        });
    }
};
