<?php

use App\Livewire\Customer\ModulePage as CustomerModulePage;
use App\Livewire\Technician\ModulePage;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function technicianBooking(int $customerId, ?int $technicianId, string $status = 'pending', ?string $scheduledAt = null): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => 'TECH-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Technician Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => $scheduledAt ? 'scheduled' : 'quick',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Repair kitchen plumbing.',
        'scheduled_at' => $scheduledAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('technicians are redirected to their workspace and see technician navigation', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($technician)
        ->get('/dashboard')
        ->assertRedirect(route('technician.module'));

    $this->actingAs($technician)
        ->get(route('technician.module'))
        ->assertOk()
        ->assertSee('Job Requests')
        ->assertSee('My Jobs')
        ->assertSee('Schedule')
        ->assertSee('Dispatch & Routes')
        ->assertSee('Earnings')
        ->assertSee('Ratings & Reviews')
        ->assertSee('Verification & Profile')
        ->assertSee('Notifications')
        ->assertSee('Support & Help')
        ->assertDontSee('System Health');
});

test('technician table actions use the shared kebab menu', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    technicianBooking($customer->id, null, 'matching');

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'job-requests']))
        ->assertOk()
        ->assertSee('data-app-table-actions-menu', false)
        ->assertSee('Accept')
        ->assertSee('Decline');
});

test('customer bookings appear in the technician incoming queue and can be accepted', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);

    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
    ]);

    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['aircon']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($customer)
        ->test(CustomerModulePage::class, ['module' => 'book-service'])
        ->set('customerName', 'Maria Santos')
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'aircon')
        ->set('bookingType', 'quick')
        ->set('address', 'Makati City')
        ->call('createBooking');

    $booking = DB::table('bookings')->where('user_id', $customer->id)->first();

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->assertSee($booking->reference)
        ->assertSee('Incoming requests')
        ->call('requestConfirmation', 'accept-request', $booking->id)
        ->call('executeConfirmedAction');

    expect(DB::table('bookings')->where('id', $booking->id)->first())
        ->assigned_technician_id->toBe($technician->id)
        ->status->toBe('assigned');
});

test('technician availability action stays visible in both states', function () {
    $offlineTechnician = User::factory()->create([
        'role' => 'technician',
        'availability_status' => 'offline',
    ]);

    $this->actingAs($offlineTechnician)
        ->get(route('technician.module'))
        ->assertOk()
        ->assertSee('Go online')
        ->assertSee('bg-[var(--color-accent)]', false);

    $availableTechnician = User::factory()->create([
        'role' => 'technician',
        'availability_status' => 'available',
    ]);

    $this->actingAs($availableTechnician)
        ->get(route('technician.module'))
        ->assertOk()
        ->assertSee('Go offline')
        ->assertSee('bg-[var(--color-accent)]', false);
});

test('technician modules expose their migrated content', function () {
    $technician = User::factory()->create(['role' => 'technician', 'account_status' => 'active']);

    foreach ([
        'job-requests' => ['Job Requests', 'Available Job Requests'],
        'my-jobs' => ['My Jobs', 'My Job Registry'],
        'schedule' => ['Schedule', 'Service Schedule'],
        'dispatch-routes' => ['Dispatch & Routes', 'Current Route'],
        'earnings' => ['Earnings', 'Earnings history'],
        'ratings-reviews' => ['Ratings & Reviews', 'Customer feedback'],
        'verification-profile' => ['Verification & Profile', 'Verification status'],
        'notifications' => ['Notifications', 'Latest updates'],
        'support' => ['Support & Help', 'Support requests'],
    ] as $slug => [$label, $content]) {
        $this->actingAs($technician)
            ->get(route('technician.module', ['module' => $slug]))
            ->assertOk()
            ->assertSee($label)
            ->assertSee($content);
    }
});

test('technicians must confirm a request before accepting it', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'account_status' => 'active']);
    $bookingId = technicianBooking($customer->id, null, 'matching');
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

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->call('requestConfirmation', 'accept-request', $bookingId)
        ->assertSet('showConfirmation', true)
        ->assertSet('confirmationTitle', 'Accept this request?')
        ->assertSee('Confirm')
        ->call('executeConfirmedAction')
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return data_get($params, 'dataset.variant') === 'success';
        });

    expect(DB::table('bookings')->where('id', $bookingId)->first())
        ->assigned_technician_id->toBe($technician->id)
        ->status->toBe('assigned');
});

test('technicians can progress assigned jobs through valid transitions', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'availability_status' => 'busy']);
    $bookingId = technicianBooking($customer->id, $technician->id, 'assigned');

    $component = Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'my-jobs'])
        ->call('requestConfirmation', 'start-job', $bookingId)
        ->call('executeConfirmedAction');

    expect(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('in_progress');

    $component
        ->call('requestConfirmation', 'complete-job', $bookingId)
        ->call('executeConfirmedAction')
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return data_get($params, 'dataset.variant') === 'success';
        });

    expect(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('completed')
        ->and(DB::table('users')->where('id', $technician->id)->value('availability_status'))->toBe('available');
});

test('non-technicians cannot open technician modules or run technician actions', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('technician.module'))
        ->assertForbidden();

    expect(fn () => (new ModulePage)->setAvailability('available'))
        ->toThrow(HttpException::class);
});
