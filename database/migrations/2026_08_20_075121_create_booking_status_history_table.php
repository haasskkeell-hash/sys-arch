<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['booking_id', 'created_at']);
            $table->index(['to_status', 'created_at']);
        });

        DB::table('bookings')
            ->select(['id', 'status', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $bookings): void {
                $now = now();

                DB::table('booking_status_histories')->insert($bookings->map(static fn (object $booking): array => [
                    'booking_id' => $booking->id,
                    'from_status' => null,
                    'to_status' => $booking->status,
                    'actor_id' => null,
                    'reason' => 'Imported from the booking status at migration time.',
                    'metadata' => null,
                    'created_at' => $booking->created_at ?? $now,
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
    }
};
