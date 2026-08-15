<?php

use App\Livewire\Customer\ModulePage as CustomerModulePage;
use App\Livewire\SuperAdmin\ModulePage as AdminModulePage;
use App\Livewire\Technician\ModulePage as TechnicianModulePage;
use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function integrationBooking(int $customerId, ?int $technicianId = null, string $status = 'matching'): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => 'INT-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Integration Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Integration test booking.',
        'scheduled_at' => null,
        'is_priority' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function approvedTechnician(User $technician): void
{
    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['plumbing']),
        'years_experience' => 5,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('workspace routes enforce the three role boundaries and admin roles redirect correctly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('technician.module'))->assertForbidden();
    $this->actingAs($admin)->get(route('customer.module'))->assertForbidden();

    $this->actingAs($technician)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($technician)->get(route('customer.module'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($customer)->get(route('technician.module'))->assertForbidden();
});

test('user and booking models expose the connected core relationships', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = integrationBooking($customer->id, $technician->id, 'completed');

    DB::table('payments')->insert([
        'booking_id' => $bookingId,
        'amount' => 1500,
        'status' => 'paid',
        'method' => 'cash',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('reviews')->insert([
        'booking_id' => $bookingId,
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
        'rating' => 5,
        'comment' => 'Excellent.',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $booking = Booking::query()->findOrFail($bookingId);

    expect($customer->bookings()->whereKey($bookingId)->exists())->toBeTrue()
        ->and($technician->assignedBookings()->whereKey($bookingId)->exists())->toBeTrue()
        ->and($booking->customer->is($customer))->toBeTrue()
        ->and($booking->technician->is($technician))->toBeTrue()
        ->and($booking->payment->amount)->toBe('1500.00')
        ->and($booking->review->rating)->toBe(5);
});

test('a customer booking can be assigned to an approved technician and completed into a payment', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'availability_status' => 'available',
    ]);
    approvedTechnician($technician);
    DB::table('service_catalog')->insert([
        'code' => 'plumbing',
        'name' => 'Plumbing repair',
        'category' => 'Plumbing',
        'base_price' => 1500,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'book-service'])
        ->set('customerName', 'Maria Santos')
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('bookingType', 'quick')
        ->set('address', 'Makati City')
        ->call('createBooking');

    $bookingId = (int) DB::table('bookings')->where('user_id', $customer->id)->value('id');

    Livewire::actingAs($admin)
        ->test(AdminModulePage::class, ['module' => 'dispatch-monitor'])
        ->call('assignBooking', $bookingId, $technician->id);

    expect(DB::table('bookings')->where('id', $bookingId)->first())
        ->assigned_technician_id->toBe($technician->id)
        ->status->toBe('assigned');

    Livewire::actingAs($technician)
        ->test(TechnicianModulePage::class, ['module' => 'my-jobs'])
        ->call('updateBookingStatus', $bookingId, 'en_route')
        ->call('updateBookingStatus', $bookingId, 'in_progress')
        ->call('updateBookingStatus', $bookingId, 'completed');

    expect(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('completed')
        ->and(DB::table('payments')->where('booking_id', $bookingId)->count())->toBe(1)
        ->and((float) DB::table('payments')->where('booking_id', $bookingId)->value('amount'))->toBe(1500.0)
        ->and(DB::table('payments')->where('booking_id', $bookingId)->value('status'))->toBe('pending');

    Livewire::actingAs($admin)
        ->test(AdminModulePage::class, ['module' => 'payments-revenue'])
        ->call('updatePaymentStatus', (int) DB::table('payments')->where('booking_id', $bookingId)->value('id'), 'paid');

    expect(DB::table('payments')->where('booking_id', $bookingId)->value('status'))->toBe('paid')
        ->and(DB::table('audit_logs')->whereIn('action', ['customer.booking_created', 'booking.updated', 'payment.created', 'payment.paid'])->count())->toBeGreaterThanOrEqual(4);
});

test('dispatch assignment rejects technicians without approved verification', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = integrationBooking($customer->id);

    $this->actingAs($admin);

    expect(fn () => (new AdminModulePage)->assignBooking($bookingId, $technician->id))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->where('id', $bookingId)->value('assigned_technician_id'))->toBeNull();
});

test('customers can join the walk-in queue and only see their own queue entries', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'walk-in-queue'])
        ->set('walkInServiceType', 'aircon')
        ->set('walkInPhone', '09170000000')
        ->set('walkInNotes', 'Please check the outdoor unit.')
        ->call('joinWalkInQueue');

    DB::table('walk_in_entries')->insert([
        'user_id' => $otherCustomer->id,
        'queue_number' => 'W-OTHER',
        'customer_name' => $otherCustomer->name,
        'customer_phone' => '09171111111',
        'service_type' => 'plumbing',
        'priority' => 'standard',
        'status' => 'waiting',
        'checked_in_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('walk_in_entries')->where('user_id', $customer->id)->count())->toBe(1);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'walk-in-queue']))
        ->assertOk()
        ->assertSee('Walk-in Queue')
        ->assertDontSee('W-OTHER');
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
