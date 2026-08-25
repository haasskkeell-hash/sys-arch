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

test('technician mobile workspace exposes the live shell and primary modules', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'overview']))
        ->assertOk()
        ->assertSee('data-app-technician-mobile-shell', false)
        ->assertSee('data-app-technician-mobile-map', false)
        ->assertSee('data-app-technician-mobile-action="job-requests"', false)
        ->assertSee('data-app-technician-mobile-action="my-jobs"', false)
        ->assertSee('data-app-technician-mobile-action="schedule"', false)
        ->assertSee('data-app-mobile-nav-item="job-requests"', false)
        ->assertSee('data-app-mobile-nav-item="my-jobs"', false)
        ->assertSee('data-app-mobile-nav-item="dispatch-routes"', false);
});

test('technician profile child modules return to profile when opened from profile', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $profileUrl = route('technician.module', ['module' => 'verification-profile']);

    $this->actingAs($technician)
        ->get($profileUrl)
        ->assertOk()
        ->assertSee('href="'.route('technician.module', ['module' => 'schedule', 'from' => 'profile']).'"', false);

    foreach (['schedule', 'earnings', 'ratings-reviews', 'notifications', 'support'] as $module) {
        $this->actingAs($technician)
            ->get(route('technician.module', ['module' => $module, 'from' => 'profile']))
            ->assertOk()
            ->assertSee('href="'.$profileUrl.'"', false)
            ->assertSee('aria-label="Back to profile"', false)
            ->assertDontSee('aria-label="Back to technician home"', false);
    }

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'schedule']))
        ->assertOk()
        ->assertSee('href="'.route('technician.module').'"', false)
        ->assertSee('aria-label="Back to technician home"', false);
});

test('technician table actions use the shared kebab menu', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'account_status' => 'active']);
    technicianBooking($customer->id, null, 'matching');
    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['plumbing']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'job-requests']))
        ->assertOk()
        ->assertSee('data-app-table-actions-menu', false)
        ->assertSee('Accept')
        ->assertSee('Decline');
});

test('technicians only see requests in their approved service categories', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);

    $plumbingBookingId = technicianBooking($customer->id, null, 'matching');
    $airconBookingId = DB::table('bookings')->insertGetId([
        'user_id' => $customer->id,
        'assigned_technician_id' => null,
        'reference' => 'TECH-AIRCON-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Technician Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'description' => 'Clean the air conditioner.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
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

    $component = Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->assertSee(DB::table('bookings')->whereKey($airconBookingId)->value('reference'))
        ->assertDontSee(DB::table('bookings')->whereKey($plumbingBookingId)->value('reference'));

    expect($component)->not->toBeNull();
});

test('technicians match legacy service category codes to current bookings', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $customer->id,
        'assigned_technician_id' => null,
        'reference' => 'TECH-ELECTRICAL-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Technician Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'electrical',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'description' => 'Repair a faulty circuit.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['EL-REPAIR']),
        'years_experience' => 5,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(ServiceCatalog::normalizeCodes(['EL-REPAIR']))->toBe(['electrical'])
        ->and(ServiceCatalog::matchingCodes(['electrical']))->toContain('EL-REPAIR');

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->assertSee(DB::table('bookings')->where('id', $bookingId)->value('reference'))
        ->call('requestConfirmation', 'accept-request', $bookingId)
        ->call('executeConfirmedAction')
        ->assertRedirect(route('technician.module'));

    expect(DB::table('bookings')->where('id', $bookingId)->first())
        ->assigned_technician_id->toBe($technician->id)
        ->status->toBe('en_route')
        ->and(DB::table('users')->where('id', $technician->id)->value('availability_status'))->toBe('busy');
});

test('technicians with a category capability see specialized service requests', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $customer->id,
        'assigned_technician_id' => null,
        'reference' => 'TECH-PLUMBING-DIAGNOSIS-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Technician Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing-diagnosis',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'description' => 'Diagnose a plumbing issue.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['plumbing']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(ServiceCatalog::matchingCodes(['plumbing']))->toContain('plumbing-diagnosis');

    $component = Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests']);

    expect($component->html())->toContain(DB::table('bookings')->whereKey($bookingId)->value('reference'));
});

test('technicians match future active service catalog categories', function () {
    ServiceCatalog::create([
        'code' => 'solar',
        'name' => 'Solar panel repair',
        'category' => 'Renewable energy',
        'is_active' => true,
    ]);

    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $customer->id,
        'assigned_technician_id' => null,
        'reference' => 'TECH-SOLAR-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Technician Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'solar',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'description' => 'Repair a solar panel.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['solar']),
        'years_experience' => 5,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->assertSee(DB::table('bookings')->where('id', $bookingId)->value('reference'));
});

test('technicians only receive and accept the exact capabilities they selected', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    $lcdBookingId = DB::table('bookings')->insertGetId([
        'user_id' => $customer->id,
        'assigned_technician_id' => null,
        'reference' => 'TECH-LCD-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Electronics Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'electronics-lcd',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'description' => 'Replace a cracked screen.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $batteryBookingId = DB::table('bookings')->insertGetId([
        'user_id' => $customer->id,
        'assigned_technician_id' => null,
        'reference' => 'TECH-BATTERY-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'Electronics Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'electronics-battery',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'description' => 'Replace a weak battery.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['electronics-lcd']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->assertSee(DB::table('bookings')->whereKey($lcdBookingId)->value('reference'))
        ->assertDontSee(DB::table('bookings')->whereKey($batteryBookingId)->value('reference'))
        ->call('acceptRequest', $lcdBookingId);

    expect(fn () => (new ModulePage)->acceptRequest($batteryBookingId))
        ->toThrow(HttpException::class);
});

test('technicians open the home module by default', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    Livewire::actingAs($technician)
        ->test(ModulePage::class)
        ->assertSet('moduleSlug', 'overview')
        ->assertSee('Ready for the next job?');
});

test('technician home exposes all swipe completion hooks', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'availability_status' => 'busy']);
    $bookingId = technicianBooking($customer->id, $technician->id, 'en_route');

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'overview']))
        ->assertOk()
        ->assertSee('data-app-technician-complete-swipe', false)
        ->assertSee('data-app-technician-complete-swipe-fill', false)
        ->assertSee('data-app-technician-complete-swipe-label', false)
        ->assertSee('data-app-technician-complete-swipe-thumb', false)
        ->assertSee('min-h-12 w-full', false)
        ->assertSeeInOrder([
            'data-app-technician-complete-swipe',
            "wire:click=\"requestConfirmation('cancel-job', {$bookingId})\"",
        ], false);
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
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->assertSee($booking->reference)
        ->assertSee('Available Job Requests')
        ->call('requestConfirmation', 'accept-request', $booking->id)
        ->call('executeConfirmedAction')
        ->assertRedirect(route('technician.module'));

    expect(DB::table('bookings')->where('id', $booking->id)->first())
        ->assigned_technician_id->toBe($technician->id)
        ->status->toBe('en_route');
});

test('technicians can decline an incoming request', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = technicianBooking($customer->id, null, 'matching');

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'job-requests'])
        ->call('declineRequest', $bookingId);

    expect(DB::table('technician_request_declines')
        ->where('technician_id', $technician->id)
        ->where('booking_id', $bookingId)
        ->exists())->toBeTrue();
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

test('technicians publish their location while they have an active job', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    technicianBooking($customer->id, $technician->id, 'en_route');

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('updateLocation', 14.61, 120.995);

    $updatedTechnician = $technician->fresh();

    expect(round((float) $updatedTechnician->latitude, 5))->toEqual(14.61)
        ->and(round((float) $updatedTechnician->longitude, 5))->toEqual(120.995)
        ->and($updatedTechnician->last_seen_at)->not->toBeNull();
});

test('technician workspace exposes a visible location failure status target', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($technician)
        ->get(route('technician.module'))
        ->assertOk()
        ->assertSee('data-app-technician-location-status', false);
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
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
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
        ->status->toBe('en_route');
});

test('technicians can complete an en route job', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'availability_status' => 'busy']);
    $bookingId = technicianBooking($customer->id, $technician->id, 'en_route');

    $component = Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'my-jobs'])
        ->assertSee('data-app-technician-complete-swipe', false)
        ->call('updateBookingStatus', $bookingId, 'completed')
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return data_get($params, 'dataset.variant') === 'success';
        });

    expect(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('completed')
        ->and(DB::table('users')->where('id', $technician->id)->value('availability_status'))->toBe('available');
});

test('technicians can cancel an accepted job and return to available status', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'availability_status' => 'busy']);
    $bookingId = technicianBooking($customer->id, $technician->id, 'en_route');

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'my-jobs'])
        ->assertSee('Cancel job')
        ->call('requestConfirmation', 'cancel-job', $bookingId)
        ->assertSet('confirmationTitle', 'Cancel this job?')
        ->call('executeConfirmedAction');

    expect(DB::table('bookings')->where('id', $bookingId)->first())
        ->status->toBe('cancelled')
        ->assigned_technician_id->toBeNull()
        ->cancellation_reason->toBe('Cancelled by technician.')
        ->and(DB::table('users')->where('id', $technician->id)->value('availability_status'))->toBe('available')
        ->and(DB::table('booking_status_histories')->where('booking_id', $bookingId)->where('to_status', 'cancelled')->value('actor_id'))->toBe($technician->id);
});

test('technicians cannot cancel another technician job or a completed job', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $assignedTechnician = User::factory()->create(['role' => 'technician']);
    $otherBookingId = technicianBooking($customer->id, $assignedTechnician->id, 'en_route');
    $completedBookingId = technicianBooking($customer->id, $technician->id, 'completed');

    $this->actingAs($technician);

    expect(fn () => (new ModulePage)->cancelBooking($otherBookingId))
        ->toThrow(HttpException::class);
    expect(fn () => (new ModulePage)->cancelBooking($completedBookingId))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->where('id', $otherBookingId)->value('status'))->toBe('en_route')
        ->and(DB::table('bookings')->where('id', $completedBookingId)->value('status'))->toBe('completed');
});

test('technicians can create a quotation for an assigned job', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = technicianBooking($customer->id, $technician->id, 'in_progress');

    Livewire::actingAs($technician)
        ->test(ModulePage::class, ['module' => 'my-jobs'])
        ->call('openQuotation', $bookingId)
        ->set('assessmentNotes', 'Replace the damaged valve.')
        ->set('laborAmount', '800')
        ->set('materialsAmount', '450')
        ->call('saveQuotation');

    $quotation = DB::table('quotations')->where('booking_id', $bookingId)->first();

    expect($quotation)
        ->technician_id->toBe($technician->id)
        ->status->toBe('awaiting_approval');
    expect((float) $quotation->total_amount)->toBe(1250.0);
});

test('technician dispatch locations link to Waze navigation', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = technicianBooking($customer->id, $technician->id, 'assigned');
    DB::table('bookings')->where('id', $bookingId)->update(['latitude' => 14.5995, 'longitude' => 120.9842]);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'dispatch-routes']))
        ->assertOk()
        ->assertSee('Navigate with Waze')
        ->assertSee('waze.com/ul?ll=', false);
});

test('non-technicians cannot open technician modules or run technician actions', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('technician.module'))
        ->assertForbidden();

    expect(fn () => (new ModulePage)->setAvailability('available'))
        ->toThrow(HttpException::class);
});
