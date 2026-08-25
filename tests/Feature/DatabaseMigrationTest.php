<?php

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\TechnicianRequestDecline;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('schema check command passes when every migration has been applied', function () {
    expect(Artisan::call('schema:check'))->toBe(0);
});

test('fresh migrations support booking history, payment, and decline writes', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    ServiceCatalog::create([
        'code' => 'plumbing',
        'name' => 'Plumbing',
        'category' => 'Home repair',
        'base_price' => 850,
        'is_active' => true,
    ]);

    $booking = Booking::create([
        'user_id' => $customer->id,
        'assigned_technician_id' => $technician->id,
        'reference' => 'MIG-BOOKING-1',
        'idempotency_key' => '11111111-1111-4111-8111-111111111111',
        'customer_name' => $customer->name,
        'customer_phone' => '+639171234567',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Makati City',
        'description' => 'Migration smoke booking.',
        'is_priority' => false,
    ]);

    $booking->recordInitialStatus($customer);
    $booking->transitionTo('assigned', $technician);
    $booking->transitionTo('en_route', $technician);
    $booking->transitionTo('completed', $technician);

    $payment = $booking->ensurePayment();
    $decline = TechnicianRequestDecline::create([
        'technician_id' => $technician->id,
        'booking_id' => $booking->id,
    ]);

    expect($booking->statusHistory()->count())->toBe(4)
        ->and($payment->booking_id)->toBe($booking->id)
        ->and($decline->booking_id)->toBe($booking->id);
});
