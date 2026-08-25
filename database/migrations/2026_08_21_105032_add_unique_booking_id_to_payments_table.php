<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'booking_id') || collect(Schema::getIndexes('payments'))->pluck('name')->contains('payments_booking_id_unique')) {
            return;
        }

        if (DB::table('payments')->select('booking_id')->groupBy('booking_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot add payments.booking_id uniqueness while duplicate payment records exist.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('booking_id', 'payments_booking_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! collect(Schema::getIndexes('payments'))->pluck('name')->contains('payments_booking_id_unique')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_booking_id_unique');
        });
    }
};
