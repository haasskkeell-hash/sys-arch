<?php

use App\Livewire\Customer\ModulePage as CustomerModulePage;
use App\Livewire\SuperAdmin\ModulePage as AdminModulePage;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function lifecycleBooking(int $customerId, ?int $technicianId, string $status, ?string $scheduledAt = null): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => 'LIFE-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Lifecycle Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => $scheduledAt === null ? 'quick' : 'scheduled',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Lifecycle test booking.',
        'scheduled_at' => $scheduledAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('booking transitions append an auditable status history record', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $booking = Booking::query()->findOrFail(lifecycleBooking($customer->id, $technician->id, 'matching'));

    $booking->transitionTo('en_route', $technician, 'Accepted request.');

    expect($booking->refresh()->status)->toBe('en_route')
        ->and(DB::table('booking_status_histories')->where('booking_id', $booking->id)->count())->toBe(1)
        ->and(DB::table('booking_status_histories')->where('booking_id', $booking->id)->first())
        ->from_status->toBe('matching')
        ->to_status->toBe('en_route')
        ->actor_id->toBe($technician->id)
        ->reason->toBe('Accepted request.');
});

test('an unassigned booking cannot be started by an admin', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $bookingId = lifecycleBooking($customer->id, null, 'assigned');

    $this->actingAs($admin);

    expect(fn () => (new AdminModulePage)->updateBookingStatus($bookingId, 'in_progress'))
        ->toThrow(HttpException::class);

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('assigned');
});

test('unassigned bookings cannot enter active or completed states', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    foreach (['en_route', 'in_progress', 'completed'] as $status) {
        $bookingId = lifecycleBooking($customer->id, null, 'assigned');

        expect(fn () => (new AdminModulePage)->updateBookingStatus($bookingId, $status))
            ->toThrow(HttpException::class);

        expect(Booking::query()->findOrFail($bookingId)->status)->toBe('assigned')
            ->and(DB::table('payments')->where('booking_id', $bookingId)->count())->toBe(0);
    }
});

test('booking lifecycle rejects invalid transitions without changing the booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $booking = Booking::query()->findOrFail(lifecycleBooking($customer->id, null, 'pending'));

    expect(fn () => $booking->transitionTo('completed', $customer, 'Invalid transition.'))
        ->toThrow(HttpException::class);

    expect($booking->refresh()->status)->toBe('pending')
        ->and(DB::table('booking_status_histories')->where('booking_id', $booking->id)->count())->toBe(0);
});

test('new customer bookings record their initial lifecycle status', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    ServiceCatalog::create([
        'code' => 'plumbing',
        'name' => 'Plumbing',
        'category' => 'Home repair',
        'base_price' => 500,
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'book-service'])
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('bookingType', 'quick')
        ->set('address', 'Makati City')
        ->call('createBooking');

    $booking = Booking::query()->whereBelongsTo($customer, 'customer')->firstOrFail();

    expect(DB::table('booking_status_histories')->where('booking_id', $booking->id)->first())
        ->from_status->toBeNull()
        ->to_status->toBe('matching')
        ->actor_id->toBe($customer->id);
});

test('customer cancellation records the reason in booking history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = lifecycleBooking($customer->id, $technician->id, 'assigned');

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'my-bookings'])
        ->call('prepareCancellation', $bookingId)
        ->set('cancellationReason', 'My schedule changed.')
        ->call('cancelBooking');

    expect(DB::table('booking_status_histories')->where('booking_id', $bookingId)->first())
        ->from_status->toBe('assigned')
        ->to_status->toBe('cancelled')
        ->reason->toBe('My schedule changed.');
});

test('scheduled bookings can be cancelled inside the configured cancellation window', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $bookingId = lifecycleBooking($customer->id, null, 'assigned', now()->addHour()->toDateTimeString());
    PlatformSetting::create([
        'key' => 'cancellation_window_hours',
        'group' => 'booking',
        'label' => 'Cancellation window',
        'value' => '2',
        'type' => 'number',
    ]);

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'my-bookings'])
        ->call('prepareCancellation', $bookingId)
        ->set('cancellationReason', 'Plans changed.')
        ->call('cancelBooking');

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('cancelled');
});

test('scheduled bookings can be cancelled regardless of the configured cancellation window', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $bookingId = lifecycleBooking($customer->id, null, 'assigned', now()->addHours(4)->toDateTimeString());
    PlatformSetting::create([
        'key' => 'cancellation_window_hours',
        'group' => 'booking',
        'label' => 'Cancellation window',
        'value' => '2',
        'type' => 'number',
    ]);

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'my-bookings'])
        ->call('prepareCancellation', $bookingId)
        ->set('cancellationReason', 'Plans changed.')
        ->call('cancelBooking');

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('cancelled');
});

test('admin can mark an overdue scheduled booking as no show with a reason', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = lifecycleBooking($customer->id, $technician->id, 'assigned', now()->subHour()->toDateTimeString());

    Livewire::actingAs($admin)
        ->test(AdminModulePage::class, ['module' => 'service-bookings'])
        ->call('updateBookingStatus', $bookingId, 'no_show', 'Customer did not appear.');

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('no_show')
        ->and(DB::table('booking_status_histories')->where('booking_id', $bookingId)->first())
        ->from_status->toBe('assigned')
        ->to_status->toBe('no_show')
        ->reason->toBe('Customer did not appear.');
});

test('admin cannot mark a future scheduled booking as no show', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $bookingId = lifecycleBooking($customer->id, null, 'assigned', now()->addHour()->toDateTimeString());

    $this->actingAs($admin);

    expect(fn () => (new AdminModulePage)->updateBookingStatus($bookingId, 'no_show', 'Customer did not appear.'))
        ->toThrow(HttpException::class);

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('assigned');
});

test('moving a payment out of paid state clears its paid timestamp', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $bookingId = lifecycleBooking($customer->id, null, 'completed');
    $payment = Payment::create([
        'booking_id' => $bookingId,
        'amount' => 500,
        'status' => 'paid',
        'method' => 'cash',
        'paid_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(AdminModulePage::class, ['module' => 'payments-revenue'])
        ->call('updatePaymentStatus', $payment->id, 'pending');

    expect($payment->refresh()->paid_at)->toBeNull();
});

test('an unpaid payment cannot be refunded by an admin', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $payment = Payment::create([
        'booking_id' => lifecycleBooking($customer->id, null, 'completed'),
        'amount' => 500,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::actingAs($admin)
        ->test(AdminModulePage::class, ['module' => 'payments-revenue'])
        ->call('updatePaymentStatus', $payment->id, 'refunded');

    expect($payment->refresh()->status)->toBe('pending');
});

test('customer and technician booking filters expose the no-show terminal state', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'my-bookings']))
        ->assertOk()
        ->assertSee('value="no_show"', false);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'my-jobs']))
        ->assertOk()
        ->assertSee('value="no_show"', false);
});
