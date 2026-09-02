<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOK close-out: per-booking ICS token, qualification answers and UTM capture
 * (bookings); custom questions + secret ICS feed token (booking pages); a
 * branch rep for territory-based assignment (seo_locations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('ics_token', 64)->nullable()->unique();
            $table->json('answers')->nullable();
            $table->json('utm')->nullable();
        });
        Schema::table('booking_pages', function (Blueprint $table) {
            $table->json('questions')->nullable();
            $table->string('ics_feed_token', 64)->nullable();
        });
        Schema::table('seo_locations', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['ics_token', 'answers', 'utm']);
        });
        Schema::table('booking_pages', function (Blueprint $table) {
            $table->dropColumn(['questions', 'ics_feed_token']);
        });
        Schema::table('seo_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
