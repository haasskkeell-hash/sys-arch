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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->unique();
        });

        Schema::table('booking_status_histories', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_status_histories', function (Blueprint $table): void {
            $table->dropUnique('booking_status_histories_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique('bookings_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
