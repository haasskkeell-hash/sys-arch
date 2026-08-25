<?php

use App\Livewire\Customer\ModulePage as CustomerModulePage;
use App\Livewire\Technician\ModulePage as TechnicianModulePage;
use App\Models\PlatformSetting;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

function invariantBooking(int $customerId, ?int $technicianId, string $status = 'matching'): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => 'INV-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Invariant Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Invariant test booking.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function invariantVerification(int $technicianId, array $serviceCategories = ['plumbing']): void
{
    DB::table('technician_verifications')->insert([
        'user_id' => $technicianId,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode($serviceCategories),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('suspended customers cannot enter customer workspace or realtime snapshots', function () {
    $customer = User::factory()->create(['role' => 'customer', 'account_status' => 'suspended']);

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('realtime.snapshot', ['scope' => 'customer-overview']))
        ->assertForbidden();
});

test('payments enforce one record per booking', function () {
    expect(collect(Schema::getIndexes('payments'))->pluck('name'))
        ->toContain('payments_booking_id_unique');
});

test('suspended technicians cannot enter technician workspace or realtime snapshots', function () {
    $technician = User::factory()->create(['role' => 'technician', 'account_status' => 'suspended']);

    $this->actingAs($technician)
        ->get(route('technician.module'))
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(route('realtime.snapshot', ['scope' => 'technician-overview']))
        ->assertForbidden();
});

test('a customer cannot create a second active booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    ServiceCatalog::create([
        'code' => 'plumbing',
        'name' => 'Plumbing',
        'category' => 'Home repair',
        'base_price' => 500,
        'is_active' => true,
    ]);

    invariantBooking($customer->id, null, 'matching');

    expect(DB::table('bookings')->where('user_id', $customer->id)->whereIn('status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])->exists())->toBeTrue();

    $this->actingAs($customer);
    $component = new CustomerModulePage;
    $component->customerPhone = '09171234567';
    $component->serviceType = 'plumbing';
    $component->bookingType = 'quick';
    $component->address = 'Makati City';

    expect(fn () => $component->createBooking())
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->where('user_id', $customer->id)->count())->toBe(1);
});

test('offline technicians cannot accept incoming requests', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'offline',
    ]);
    $bookingId = invariantBooking($customer->id, null);
    invariantVerification($technician->id);

    $this->actingAs($technician);

    expect(fn () => (new TechnicianModulePage)->acceptRequest($bookingId))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->whereKey($bookingId)->value('assigned_technician_id'))->toBeNull();
});

test('technicians can accept only requests matching their approved service categories', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    $bookingId = invariantBooking($customer->id, null);
    invariantVerification($technician->id, ['aircon']);

    $this->actingAs($technician);

    expect(fn () => (new TechnicianModulePage)->acceptRequest($bookingId))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->whereKey($bookingId)->value('assigned_technician_id'))->toBeNull();
});

test('technicians cannot accept requests beyond the configured active job limit', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    invariantBooking($customer->id, $technician->id, 'en_route');
    $bookingId = invariantBooking($customer->id, null);
    invariantVerification($technician->id);
    PlatformSetting::create([
        'key' => 'max_active_jobs',
        'group' => 'operations',
        'label' => 'Maximum active jobs per technician',
        'value' => '1',
        'type' => 'number',
    ]);

    $this->actingAs($technician);

    expect(fn () => (new TechnicianModulePage)->acceptRequest($bookingId))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->whereKey($bookingId)->value('assigned_technician_id'))->toBeNull();
});

test('technicians cannot go offline while an active job is assigned', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    invariantBooking($customer->id, $technician->id, 'en_route');

    $this->actingAs($technician);

    expect(fn () => (new TechnicianModulePage)->setAvailability('offline'))
        ->toThrow(HttpException::class);

    expect($technician->refresh()->availability_status)->toBe('available');
});
