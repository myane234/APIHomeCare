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
            // Drop old columns
            $table->dropColumn([
                'footer_facebook',
                'footer_instagram',
                'footer_twitter',
                'footer_youtube'
            ]);

            // Add dynamic JSON column
            $table->json('footer_socials')->nullable()->after('footer_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_managements', function (Blueprint $table) {
            $table->dropColumn('footer_socials');

            $table->string('footer_facebook')->nullable();
            $table->string('footer_instagram')->nullable();
            $table->string('footer_twitter')->nullable();
            $table->string('footer_youtube')->nullable();
        });
    }
};
