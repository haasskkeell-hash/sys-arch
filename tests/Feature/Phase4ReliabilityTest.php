<?php

use App\Livewire\Customer\ModulePage as CustomerModulePage;
use App\Livewire\Technician\ModulePage as TechnicianModulePage;
use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function phase4Booking(int $customerId, ?int $technicianId, string $status): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => 'P4-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Phase Four Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Phase four reliability test booking.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function phase4Verification(int $technicianId): void
{
    DB::table('technician_verifications')->insert([
        'user_id' => $technicianId,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['plumbing']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('replaying a customer booking request creates one booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    ServiceCatalog::create([
        'code' => 'plumbing',
        'name' => 'Plumbing',
        'category' => 'Home repair',
        'base_price' => 500,
        'is_active' => true,
    ]);

    $requestKey = Str::uuid()->toString();
    $component = Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'overview'])
        ->set('bookingIdempotencyKey', $requestKey)
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('address', 'Makati City')
        ->call('createBooking');

    $component
        ->set('bookingIdempotencyKey', $requestKey)
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('address', 'Makati City')
        ->call('createBooking');

    expect(DB::table('bookings')->where('user_id', $customer->id)->count())->toBe(1)
        ->and(DB::table('bookings')->where('user_id', $customer->id)->value('idempotency_key'))->toBe($requestKey);
});

test('a booking idempotency key cannot be reused by another customer', function () {
    $firstCustomer = User::factory()->create(['role' => 'customer']);
    $secondCustomer = User::factory()->create(['role' => 'customer']);
    ServiceCatalog::create([
        'code' => 'plumbing',
        'name' => 'Plumbing',
        'category' => 'Home repair',
        'base_price' => 500,
        'is_active' => true,
    ]);

    $requestKey = Str::uuid()->toString();

    Livewire::actingAs($firstCustomer)
        ->test(CustomerModulePage::class, ['module' => 'overview'])
        ->set('bookingIdempotencyKey', $requestKey)
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('address', 'Makati City')
        ->call('createBooking');

    $this->actingAs($secondCustomer);
    expect(auth()->id())->toBe($secondCustomer->id);

    $secondComponent = new CustomerModulePage;
    $secondComponent->bookingIdempotencyKey = $requestKey;
    $secondComponent->customerPhone = '09171234567';
    $secondComponent->serviceType = 'plumbing';
    $secondComponent->address = 'Makati City';

    expect(fn () => $secondComponent->createBooking())
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->where('user_id', $secondCustomer->id)->count())->toBe(0);
});

test('replaying a customer cancellation does not create a second status change', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $bookingId = phase4Booking($customer->id, null, 'assigned');
    $component = Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'my-bookings'])
        ->call('prepareCancellation', $bookingId);
    $requestKey = $component->get('cancellationIdempotencyKey');

    $component
        ->set('cancellationReason', 'Plans changed.')
        ->call('cancelBooking');

    Booking::query()->findOrFail($bookingId)->transitionTo('cancelled', $customer, 'Plans changed.', [], $requestKey);

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('cancelled')
        ->and(DB::table('booking_status_histories')->where('booking_id', $bookingId)->where('to_status', 'cancelled')->count())->toBe(1);
});

test('replaying a technician acceptance keeps one assignment and status history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'availability_status' => 'available',
    ]);
    $bookingId = phase4Booking($customer->id, null, 'matching');
    phase4Verification($technician->id);
    $requestKey = Str::uuid()->toString();

    $component = Livewire::actingAs($technician)
        ->test(TechnicianModulePage::class, ['module' => 'overview'])
        ->call('acceptRequest', $bookingId, $requestKey)
        ->call('acceptRequest', $bookingId, $requestKey);

    expect(Booking::query()->findOrFail($bookingId)->assigned_technician_id)->toBe($technician->id)
        ->and(DB::table('booking_status_histories')->where('booking_id', $bookingId)->where('to_status', 'en_route')->count())->toBe(1)
        ->and($customer->notifications()->where('type', BookingStatusChanged::class)->count())->toBe(1);
});

test('replaying technician completion does not duplicate payment or status history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'availability_status' => 'available',
    ]);
    $bookingId = phase4Booking($customer->id, $technician->id, 'en_route');
    $requestKey = Str::uuid()->toString();

    $component = Livewire::actingAs($technician)
        ->test(TechnicianModulePage::class, ['module' => 'overview'])
        ->call('updateBookingStatus', $bookingId, 'completed', $requestKey)
        ->call('updateBookingStatus', $bookingId, 'completed', $requestKey);

    expect(Booking::query()->findOrFail($bookingId)->status)->toBe('completed')
        ->and(DB::table('booking_status_histories')->where('booking_id', $bookingId)->where('to_status', 'completed')->count())->toBe(1)
        ->and(DB::table('payments')->where('booking_id', $bookingId)->count())->toBe(1)
        ->and($customer->notifications()->where('type', BookingStatusChanged::class)->count())->toBe(1);
});

test('invalid idempotency keys are rejected at booking mutation boundaries', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);
    $bookingId = phase4Booking($customer->id, $technician->id, 'en_route');

    $this->actingAs($technician);

    expect(fn () => (new TechnicianModulePage)->updateBookingStatus($bookingId, 'completed', 'not-a-uuid'))
        ->toThrow(HttpException::class);
});

test('realtime client contains offline recovery and bounded retry backoff', function () {
    $script = file_get_contents(resource_path('js/realtime.js'));

    expect($script)
        ->toContain("window.addEventListener('online'")
        ->toContain("window.addEventListener('offline'")
        ->toContain('Math.min(state.interval * 2 ** state.failureCount, 30000)');
});

test('technician location failures expose a structured browser event', function () {
    $script = file_get_contents(resource_path('js/technician-location.js'));

    expect($script)
        ->toContain('technician_location_update_failed')
        ->toContain('Location updates are paused');
});

test('realtime and map failures expose structured browser events', function () {
    $realtime = file_get_contents(resource_path('js/realtime.js'));
    $map = file_get_contents(resource_path('js/customer-map.js'));

    expect($realtime)
        ->toContain('realtime_poll_failed')
        ->toContain('fixtrack:realtime-error');

    expect($map)
        ->toContain('map_route_failed')
        ->toContain('fixtrack:map-error')
        ->toContain('map_tiles_failed')
        ->toContain('map_leaflet_load_failed');
});
