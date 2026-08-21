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
            // Gabung Mitra
            $table->string('mitra_banner')->nullable()->after('komitmen');
            $table->text('mitra_text_banner')->nullable()->after('mitra_banner');
            $table->text('mitra_description')->nullable()->after('mitra_text_banner');

            // Footer
            $table->text('footer_description')->nullable()->after('mitra_description');
            $table->string('footer_phone')->nullable()->after('footer_description');
            $table->string('footer_email')->nullable()->after('footer_phone');
            $table->text('footer_address')->nullable()->after('footer_email');
            $table->string('footer_facebook')->nullable()->after('footer_address');
            $table->string('footer_instagram')->nullable()->after('footer_facebook');
            $table->string('footer_twitter')->nullable()->after('footer_instagram');
            $table->string('footer_youtube')->nullable()->after('footer_twitter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_managements', function (Blueprint $table) {
            $table->dropColumn([
                'mitra_banner',
                'mitra_text_banner',
                'mitra_description',
                'footer_description',
                'footer_phone',
                'footer_email',
                'footer_address',
                'footer_facebook',
                'footer_instagram',
                'footer_twitter',
                'footer_youtube'
            ]);
        });
    }
};
