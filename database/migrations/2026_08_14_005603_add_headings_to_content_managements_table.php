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
            $table->string('promo_heading')->nullable()->after('home_description');
            $table->text('promo_text')->nullable()->after('promo_heading');
            
            $table->string('artikel_heading')->nullable()->after('promo_text');
            $table->text('artikel_text')->nullable()->after('artikel_heading');
            
            $table->string('layanan_heading')->nullable()->after('artikel_text');
            $table->text('layanan_text')->nullable()->after('layanan_heading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_managements', function (Blueprint $table) {
            $table->dropColumn([
                'promo_heading',
                'promo_text',
                'artikel_heading',
                'artikel_text',
                'layanan_heading',
                'layanan_text'
            ]);
        });
    }
};
