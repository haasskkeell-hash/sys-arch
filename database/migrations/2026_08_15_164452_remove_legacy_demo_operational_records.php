<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyBookingIds = DB::table('bookings')
                ->where('reference', 'like', 'DEMO-%')
                ->pluck('id');

            if ($legacyBookingIds->isNotEmpty()) {
                DB::table('payments')->whereIn('booking_id', $legacyBookingIds)->delete();
                DB::table('reviews')->whereIn('booking_id', $legacyBookingIds)->delete();
                DB::table('bookings')->whereIn('id', $legacyBookingIds)->delete();
            }

            DB::table('support_tickets')
                ->where('reference', 'like', 'SUP-DEMO-%')
                ->delete();

            DB::table('walk_in_entries')
                ->whereIn('queue_number', ['W001', 'W002', 'W003'])
                ->whereIn('customer_name', ['Maria Santos', 'Jose Reyes', 'Ana Cruz'])
                ->whereDate('created_at', '2026-08-12')
                ->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Legacy demo records are intentionally not restored after cleanup.
         */
    }
};
