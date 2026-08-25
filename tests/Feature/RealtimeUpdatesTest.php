<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('realtime snapshots are lightweight, versioned, and role-scoped', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $response = $this->actingAs($admin)->get(route('realtime.snapshot', ['scope' => 'admin-dispatch-monitor']));

    $response->assertOk()
        ->assertJsonStructure(['scope', 'version', 'changed', 'counts'])
        ->assertJsonPath('scope', 'admin-dispatch-monitor')
        ->assertJsonMissingPath('bookings');

    $version = $response->json('version');

    $this->actingAs($admin)
        ->get(route('realtime.snapshot', ['scope' => 'admin-dispatch-monitor', 'since' => $version]))
        ->assertOk()
        ->assertJsonPath('changed', false);

    $this->actingAs($customer)
        ->get(route('realtime.snapshot', ['scope' => 'admin-dispatch-monitor']))
        ->assertForbidden();
});

test('unchanged realtime snapshots only return version metadata', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $initial = $this->actingAs($admin)
        ->get(route('realtime.snapshot', ['scope' => 'admin-dispatch-monitor']))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('realtime.snapshot', [
            'scope' => 'admin-dispatch-monitor',
            'since' => $initial->json('version'),
        ]))
        ->assertOk()
        ->assertJsonPath('changed', false)
        ->assertJsonMissingPath('counts');
});

test('customer snapshots only expose that customer booking counters and detect updates', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $createdAt = now()->subMinute();

    foreach ([$customer->id, $otherCustomer->id] as $index => $userId) {
        DB::table('bookings')->insert([
            'user_id' => $userId,
            'reference' => 'FT-REALTIME-00'.($index + 1),
            'customer_name' => 'Realtime Customer',
            'customer_phone' => '09170000000',
            'service_type' => 'aircon',
            'booking_type' => 'quick',
            'status' => 'matching',
            'address' => 'Realtime address',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $response = $this->actingAs($customer)->get(route('realtime.snapshot', ['scope' => 'customer-my-bookings']));

    $response->assertOk()
        ->assertJsonPath('counts.active', 1)
        ->assertJsonMissingPath('bookings');

    $version = $response->json('version');
    DB::table('bookings')->where('user_id', $customer->id)->update(['status' => 'completed', 'updated_at' => now()->addMinute()]);

    $this->actingAs($customer)
        ->get(route('realtime.snapshot', ['scope' => 'customer-my-bookings', 'since' => $version]))
        ->assertOk()
        ->assertJsonPath('changed', true)
        ->assertJsonPath('counts.active', 0)
        ->assertJsonPath('counts.completed', 1);
});

test('customer active booking tracking returns the assigned technician location and detects movement', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'avatar_path' => 'avatars/technician.png',
        'latitude' => 14.6000000,
        'longitude' => 120.9850000,
        'last_seen_at' => now()->subMinute(),
    ]);
    $otherTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Other Technician']);
    $createdAt = now()->subMinute();

    foreach ([[$customer->id, $technician->id, 'TECH-OWNED'], [$otherCustomer->id, $otherTechnician->id, 'TECH-OTHER']] as [$userId, $technicianId, $reference]) {
        DB::table('bookings')->insert([
            'user_id' => $userId,
            'assigned_technician_id' => $technicianId,
            'reference' => $reference,
            'customer_name' => 'Realtime Customer',
            'customer_phone' => '09170000000',
            'service_type' => 'aircon',
            'booking_type' => 'quick',
            'status' => 'en_route',
            'address' => 'Realtime address',
            'latitude' => 14.5995000,
            'longitude' => 120.9842000,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $response = $this->actingAs($customer)->get(route('realtime.snapshot', ['scope' => 'customer-active-booking']));

    $response->assertOk()
        ->assertJsonStructure(['scope', 'version', 'changed', 'tracking'])
        ->assertJsonPath('tracking.reference', 'TECH-OWNED')
        ->assertJsonPath('tracking.status', 'en_route')
        ->assertJsonPath('tracking.customer.latitude', 14.5995)
        ->assertJsonPath('tracking.technician.name', $technician->name)
        ->assertJsonPath('tracking.technician.avatar', $technician->avatarUrl())
        ->assertJsonPath('tracking.technician.initials', $technician->initials())
        ->assertJsonPath('tracking.technician.latitude', 14.6)
        ->assertJsonMissing(['name' => 'Other Technician'])
        ->assertJsonMissing(['reference' => 'TECH-OTHER']);

    $version = $response->json('version');
    DB::table('users')->where('id', $technician->id)->update([
        'latitude' => 14.6100000,
        'longitude' => 120.9950000,
        'last_seen_at' => now()->addMinute(),
        'updated_at' => now()->addMinute(),
    ]);

    $this->actingAs($customer)
        ->get(route('realtime.snapshot', ['scope' => 'customer-active-booking', 'since' => $version]))
        ->assertOk()
        ->assertJsonPath('changed', true)
        ->assertJsonPath('tracking.technician.latitude', 14.61)
        ->assertJsonPath('tracking.technician.longitude', 120.995);
});

test('polling tiers are present only on live or medium-urgency pages', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin)
        ->get(route('admin.module', ['module' => 'dispatch-monitor']))
        ->assertOk()
        ->assertSee('data-realtime-scope="admin-dispatch-monitor"', false)
        ->assertSee('data-realtime-interval="5000"', false);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'job-requests']))
        ->assertOk()
        ->assertSee('data-realtime-scope="technician-job-requests"', false)
        ->assertSee('data-realtime-interval="5000"', false);

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertOk()
        ->assertSee('data-realtime-scope="customer-overview"', false)
        ->assertSee('data-realtime-interval="1000"', false);

    $this->actingAs($admin)
        ->get(route('admin.module', ['module' => 'reports-analytics']))
        ->assertOk()
        ->assertDontSee('data-realtime-scope', false);

    $this->actingAs($admin)
        ->get(route('admin.module', ['module' => 'service-bookings']))
        ->assertOk()
        ->assertSee('data-realtime-scope="admin-service-bookings"', false)
        ->assertSee('data-realtime-interval="60000"', false);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'job-requests']))
        ->assertOk()
        ->assertSee('data-realtime-scope="technician-job-requests"', false)
        ->assertSee('data-realtime-interval="5000"', false)
        ->assertDontSee('wire:poll', false);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'earnings']))
        ->assertOk()
        ->assertSee('data-realtime-scope="technician-earnings"', false)
        ->assertSee('data-realtime-interval="60000"', false);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'verification-profile']))
        ->assertOk()
        ->assertSee('data-realtime-scope="technician-verification-profile"', false)
        ->assertSee('data-realtime-interval="60000"', false);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'my-bookings']))
        ->assertOk()
        ->assertSee('data-realtime-scope="customer-my-bookings"', false)
        ->assertSee('data-realtime-interval="10000"', false);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'payments']))
        ->assertOk()
        ->assertSee('data-realtime-scope="customer-payments"', false)
        ->assertSee('data-realtime-interval="60000"', false);
});

test('realtime counters use a short-lived versioned cache entry', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($admin)
        ->get(route('realtime.snapshot', ['scope' => 'admin-dispatch-monitor']))
        ->assertOk();

    expect(Cache::has('fixtrack:realtime:admin-dispatch-monitor:global:'.$response->json('version')))->toBeTrue();
});

test('dashboard and report aggregates are stored in short-lived cache entries', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.module', ['module' => 'reports-analytics']))->assertOk();
    $this->actingAs($admin)->get(route('admin.module', ['module' => 'payments-revenue']))->assertOk();

    expect(Cache::has('fixtrack:dashboard:stats:none'))->toBeTrue()
        ->and(Cache::has('fixtrack:analytics:metrics:none'))->toBeTrue()
        ->and(Cache::has('fixtrack:payments:metrics:none'))->toBeTrue();
});

test('realtime query indexes are installed for status, ownership, and timestamps', function () {
    foreach ([
        'bookings_status_updated_index',
        'bookings_technician_status_updated_index',
        'bookings_user_updated_index',
    ] as $indexName) {
        expect(collect(Schema::getIndexes('bookings'))->pluck('name'))->toContain($indexName);
    }

    expect(collect(Schema::getIndexes('walk_in_entries'))->pluck('name'))
        ->toContain('walk_in_entries_status_updated_index');
    expect(collect(Schema::getIndexes('reviews'))->pluck('name'))
        ->toContain('reviews_technician_status_updated_index');
    expect(collect(Schema::getIndexes('reviews'))->pluck('name'))
        ->toContain('reviews_customer_status_updated_index');
    expect(collect(Schema::getIndexes('technician_documents'))->pluck('name'))
        ->toContain('technician_documents_status_updated_index');
    expect(collect(Schema::getIndexes('service_counters'))->pluck('name'))
        ->toContain('service_counters_status_updated_index');
    expect(collect(Schema::getIndexes('support_tickets'))->pluck('name'))
        ->toContain('support_tickets_user_status_updated_index');
    expect(collect(Schema::getIndexes('audit_logs'))->pluck('name'))
        ->toContain('audit_logs_user_created_index');
});
