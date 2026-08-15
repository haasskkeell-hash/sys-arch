<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('super admins can see the FixTrack operations overview', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));

    $response->assertOk();

    foreach ([
        'Active bookings',
        'Bookings today',
        'Completion rate',
        'Action required',
        'Booking activity',
        'Needs attention',
        'Recent bookings',
        'Top services',
    ] as $label) {
        $response->assertSee($label);
    }
});

test('super admin dashboard renders booking activity lines and status rings', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $createdAt = now()->subDay();

    foreach (['in_progress', 'completed', 'cancelled'] as $index => $status) {
        DB::table('bookings')->insert([
            'user_id' => $superAdmin->id,
            'reference' => 'FT-CHART-00'.($index + 1),
            'customer_name' => 'Chart Customer '.($index + 1),
            'customer_phone' => '0917000000'.($index + 1),
            'service_type' => 'aircon',
            'booking_type' => 'scheduled',
            'status' => $status,
            'address' => 'Chart address',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-super-admin-booking-activity-chart', false)
        ->assertSee('data-super-admin-booking-series="active"', false)
        ->assertSee('data-super-admin-booking-series="completed"', false)
        ->assertSee('data-super-admin-booking-series="cancelled"', false)
        ->assertSee('data-super-admin-booking-status-chart', false)
        ->assertSee('data-super-admin-status-ring="waiting"', false)
        ->assertSee('data-super-admin-status-ring="active"', false)
        ->assertSee('data-super-admin-status-ring="completed"', false)
        ->assertSee('stroke-dasharray', false);
});

test('super admin dashboard exposes advanced chart details without clipped legends', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-super-admin-chart-summary', false)
        ->assertSee('data-super-admin-booking-series-type="smooth"', false)
        ->assertSee('data-super-admin-booking-area="active"', false)
        ->assertSee('data-super-admin-booking-tooltip', false)
        ->assertSee('data-super-admin-booking-activity-layout="compact"', false)
        ->assertSee('data-super-admin-status-chart-layout="responsive"', false)
        ->assertSee('data-super-admin-status-legend', false)
        ->assertSee('data-super-admin-status-layout="compact"', false)
        ->assertSee('min-h-[12rem]', false)
        ->assertSee('data-super-admin-status-chart-center="open"', false);
});

test('regular users cannot access the Super Admin dashboard', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('super admins are sent to their dashboard after signing in', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('super admin pages expose the shared sidebar and section action menus', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    DB::table('bookings')->insert([
        'user_id' => $superAdmin->id,
        'reference' => 'FT-DASHBOARD-001',
        'customer_name' => 'Dashboard Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'scheduled',
        'status' => 'pending',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Search modules')
        ->assertSee('data-flux-sidebar-collapse', false)
        ->assertSee('data-flux-sidebar-group', false)
        ->assertSee('data-super-admin-section-menu', false)
        ->assertSee('data-super-admin-dashboard-table', false)
        ->assertSee('whitespace-normal break-words', false);

    $this->actingAs($superAdmin)
        ->get('/admin/system-health')
        ->assertOk()
        ->assertSee('data-flux-sidebar-collapse', false)
        ->assertSee('data-flux-sidebar-group', false)
        ->assertSee('Service checks')
        ->assertSee('Refresh section')
        ->assertSee('data-super-admin-section-menu', false);
});

test('super admin module icons remain visible when the sidebar is collapsed', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $html = $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-super-admin-sidebar-item="system-health"');
    expect($html)->toContain('in-data-flux-sidebar-collapsed-desktop:w-10');

    $sidebarStyles = file_get_contents(resource_path('css/app.css'));

    expect($sidebarStyles)->toContain('ui-sidebar[data-flux-sidebar-collapsed-desktop] [data-flux-sidebar-group]');
    expect($sidebarStyles)->toContain('ui-sidebar[data-flux-sidebar-collapsed-desktop] [data-flux-sidebar-group] > div:first-child:not(:only-child)');
});
