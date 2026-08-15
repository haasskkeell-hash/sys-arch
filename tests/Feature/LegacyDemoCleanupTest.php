<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalkInEntry;
use Carbon\CarbonImmutable;

test('legacy demo cleanup removes marked records and preserves live records', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $legacyBooking = Booking::create([
        'user_id' => $customer->id,
        'reference' => 'DEMO-0001',
        'customer_name' => 'Customer',
        'customer_phone' => '09170000001',
        'service_type' => 'AC-CLEAN',
        'booking_type' => 'quick',
        'status' => 'completed',
        'address' => 'Legacy demo address',
    ]);
    $liveBooking = Booking::create([
        'user_id' => $customer->id,
        'assigned_technician_id' => $technician->id,
        'reference' => 'FX-LIVE-0001',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000002',
        'service_type' => 'AC-CLEAN',
        'booking_type' => 'quick',
        'status' => 'completed',
        'address' => 'Live customer address',
    ]);

    Payment::create(['booking_id' => $legacyBooking->id, 'amount' => 850, 'status' => 'paid']);
    Payment::create(['booking_id' => $liveBooking->id, 'amount' => 950, 'status' => 'paid']);
    Review::create([
        'booking_id' => $legacyBooking->id,
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
        'rating' => 5,
        'status' => 'published',
    ]);

    SupportTicket::create([
        'reference' => 'SUP-DEMO-001',
        'user_id' => $customer->id,
        'subject' => 'Legacy demo ticket',
        'category' => 'booking',
        'status' => 'open',
    ]);
    SupportTicket::create([
        'reference' => 'SUP-LIVE-001',
        'user_id' => $customer->id,
        'subject' => 'Live support ticket',
        'category' => 'booking',
        'status' => 'open',
    ]);

    $seedDate = CarbonImmutable::create(2026, 8, 12, 12);
    $legacyWalkIn = WalkInEntry::create([
        'queue_number' => 'W001',
        'customer_name' => 'Maria Santos',
        'customer_phone' => '09170000003',
        'service_type' => 'AC-CLEAN',
        'checked_in_at' => $seedDate,
    ]);
    $legacyWalkIn->created_at = $seedDate;
    $legacyWalkIn->save();
    WalkInEntry::create([
        'queue_number' => 'W004',
        'customer_name' => 'Live Walk-in',
        'customer_phone' => '09170000004',
        'service_type' => 'AC-CLEAN',
        'checked_in_at' => now(),
    ]);

    $migrationFiles = glob(base_path('database/migrations/*remove_legacy_demo_operational_records.php'));
    expect($migrationFiles)->not->toBeEmpty();

    $migration = require $migrationFiles[0];
    $migration->up();

    expect(Booking::query()->where('reference', 'DEMO-0001')->exists())->toBeFalse()
        ->and(Booking::query()->whereKey($liveBooking->id)->exists())->toBeTrue()
        ->and(Payment::query()->where('booking_id', $legacyBooking->id)->exists())->toBeFalse()
        ->and(Payment::query()->where('booking_id', $liveBooking->id)->exists())->toBeTrue()
        ->and(Review::query()->where('booking_id', $legacyBooking->id)->exists())->toBeFalse()
        ->and(SupportTicket::query()->where('reference', 'SUP-DEMO-001')->exists())->toBeFalse()
        ->and(SupportTicket::query()->where('reference', 'SUP-LIVE-001')->exists())->toBeTrue()
        ->and(WalkInEntry::query()->where('queue_number', 'W001')->exists())->toBeFalse()
        ->and(WalkInEntry::query()->where('queue_number', 'W004')->exists())->toBeTrue();
});
