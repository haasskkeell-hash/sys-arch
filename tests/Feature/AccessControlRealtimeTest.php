<?php

use App\Livewire\Technician\ModulePage as TechnicianModulePage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

function accessControlBooking(int $customerId, int $technicianId, string $status = 'en_route', ?string $reference = null): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => $reference ?? 'ACL-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Access Control Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Access control test booking.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('realtime scopes reject users from the wrong role boundary', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    foreach (['admin-dashboard', 'admin-dispatch-monitor'] as $scope) {
        $this->actingAs($technician)->get(route('realtime.snapshot', ['scope' => $scope]))->assertForbidden();
        $this->actingAs($customer)->get(route('realtime.snapshot', ['scope' => $scope]))->assertForbidden();
    }

    foreach (['technician-overview', 'technician-my-jobs'] as $scope) {
        $this->actingAs($admin)->get(route('realtime.snapshot', ['scope' => $scope]))->assertForbidden();
        $this->actingAs($customer)->get(route('realtime.snapshot', ['scope' => $scope]))->assertForbidden();
    }

    foreach (['customer-overview', 'customer-active-booking'] as $scope) {
        $this->actingAs($admin)->get(route('realtime.snapshot', ['scope' => $scope]))->assertForbidden();
        $this->actingAs($technician)->get(route('realtime.snapshot', ['scope' => $scope]))->assertForbidden();
    }
});

test('realtime snapshots reject unknown scopes instead of falling through to a default payload', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('realtime.snapshot', ['scope' => 'customer-everything']))
        ->assertNotFound();
});

test('customer realtime versions ignore another customers booking updates', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $createdAt = now()->subMinute();

    foreach ([$customer->id, $otherCustomer->id] as $index => $customerId) {
        DB::table('bookings')->insert([
            'user_id' => $customerId,
            'assigned_technician_id' => null,
            'reference' => 'ACL-CUST-'.($index + 1),
            'customer_name' => 'Access Control Customer',
            'customer_phone' => '09170000000',
            'service_type' => 'plumbing',
            'booking_type' => 'quick',
            'status' => 'matching',
            'address' => 'Quezon City',
            'description' => null,
            'scheduled_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $initial = $this->actingAs($customer)
        ->get(route('realtime.snapshot', ['scope' => 'customer-my-bookings']))
        ->assertOk();

    DB::table('bookings')->where('user_id', $otherCustomer->id)->update([
        'status' => 'completed',
        'updated_at' => now()->addMinute(),
    ]);

    $this->actingAs($customer)
        ->get(route('realtime.snapshot', [
            'scope' => 'customer-my-bookings',
            'since' => $initial->json('version'),
        ]))
        ->assertOk()
        ->assertJsonPath('changed', false)
        ->assertJsonMissingPath('counts');
});

test('technicians cannot mutate a booking assigned to another technician', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'technician']);
    $attacker = User::factory()->create(['role' => 'technician']);
    $bookingId = accessControlBooking($customer->id, $owner->id, 'en_route');

    $this->actingAs($attacker);

    expect(fn () => (new TechnicianModulePage)->updateBookingStatus($bookingId, 'completed'))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('en_route');
});

test('realtime polling uses a dedicated throttle boundary', function () {
    $middleware = app('router')->getRoutes()->getByName('realtime.snapshot')->gatherMiddleware();

    expect($middleware)->toContain('throttle:realtime');
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
