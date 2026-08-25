<?php

use App\Livewire\SuperAdmin\ModulePage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('super admins see every super admin sidebar module', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));

    $response->assertOk();

    foreach ([
        'Dashboard',
        'System Health',
        'Users & Roles',
        'Technician Verification',
        'Service Bookings',
        'Dispatch Monitor',
        'Walk-in Queue',
        'Payments & Revenue',
        'Ratings & Reviews',
        'Reports & Analytics',
        'Support & Disputes',
        'Audit Logs',
        'Service Catalog',
        'Platform Settings',
    ] as $module) {
        $response->assertSee($module);
    }
});

test('regular users do not see super admin sidebar modules', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('customer.module'));

    $this->actingAs($user)->get(route('customer.module'))->assertOk()
        ->assertSee('My Bookings')
        ->assertSee('Support & Help')
        ->assertDontSee('System Health')
        ->assertDontSee('Users & Roles')
        ->assertDontSee('Service Catalog');
});

test('super admin sidebar modules link to their pages', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));

    foreach ([
        'system-health' => 'System Health',
        'users-roles' => 'Users & Roles',
        'technician-verification' => 'Technician Verification',
        'service-bookings' => 'Service Bookings',
        'dispatch-monitor' => 'Dispatch Monitor',
        'walk-in-queue' => 'Walk-in Queue',
        'payments-revenue' => 'Payments & Revenue',
        'ratings-reviews' => 'Ratings & Reviews',
        'reports-analytics' => 'Reports & Analytics',
        'support-disputes' => 'Support & Disputes',
        'audit-logs' => 'Audit Logs',
        'service-catalog' => 'Service Catalog',
        'platform-settings' => 'Platform Settings',
    ] as $slug => $label) {
        $response->assertSee($label)
            ->assertSee('/admin/'.$slug, false);
    }
});

test('super admins can open every sidebar module', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    foreach ([
        'system-health' => 'System Health',
        'users-roles' => 'Users & Roles',
        'technician-verification' => 'Technician Verification',
        'service-bookings' => 'Service Bookings',
        'dispatch-monitor' => 'Dispatch Monitor',
        'walk-in-queue' => 'Walk-in Queue',
        'payments-revenue' => 'Payments & Revenue',
        'ratings-reviews' => 'Ratings & Reviews',
        'reports-analytics' => 'Reports & Analytics',
        'support-disputes' => 'Support & Disputes',
        'audit-logs' => 'Audit Logs',
        'service-catalog' => 'Service Catalog',
        'platform-settings' => 'Platform Settings',
    ] as $slug => $label) {
        $this->actingAs($superAdmin)
            ->get('/admin/'.$slug)
            ->assertOk()
            ->assertSee($label);
    }
});

test('super admin module headers use contextual view badges', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    foreach ([
        'system-health' => 'Operational',
        'users-roles' => 'Access control',
        'technician-verification' => 'Review queue',
        'service-bookings' => 'Operations',
        'dispatch-monitor' => 'Live dispatch',
        'walk-in-queue' => 'Live queue',
        'payments-revenue' => 'Finance',
        'ratings-reviews' => 'Quality monitor',
        'reports-analytics' => 'Analytics',
        'support-disputes' => 'Attention queue',
        'audit-logs' => 'Governance',
        'service-catalog' => 'Catalog control',
        'platform-settings' => 'Live controls',
    ] as $slug => $state) {
        $this->actingAs($superAdmin)
            ->get('/admin/'.$slug)
            ->assertOk()
            ->assertSee('data-super-admin-module-state="'.$state.'"', false);
    }
});

test('regular users cannot open super admin modules directly', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get('/admin/system-health')
        ->assertForbidden();
});

test('each super admin module exposes its operational content', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    foreach ([
        'system-health' => ['Service checks', 'Resource snapshot'],
        'users-roles' => ['User directory', 'Total accounts'],
        'technician-verification' => ['Verification queue', 'Pending review'],
        'service-bookings' => ['Service booking registry', 'Total bookings'],
        'dispatch-monitor' => ['Live dispatch queue', 'Available technicians'],
        'walk-in-queue' => ['Live walk-in queue', 'Available counters'],
        'payments-revenue' => ['Payment transactions', 'Total collected'],
        'ratings-reviews' => ['Ratings & reviews', 'Average rating'],
        'reports-analytics' => ['Booking performance', 'Completion rate'],
        'support-disputes' => ['Support queue', 'Open tickets'],
        'audit-logs' => ['Audit trail', 'Total events'],
        'service-catalog' => ['Service catalog', 'Active services'],
        'platform-settings' => ['Platform controls', 'Total controls'],
    ] as $slug => $content) {
        $response = $this->actingAs($superAdmin)->get('/admin/'.$slug);

        $response->assertOk()
            ->assertSee($content[0])
            ->assertSee($content[1]);

        if ($slug === 'users-roles') {
            $response->assertSee('Make technician');
        }
    }
});

test('reports analytics exposes a booking trend graph and export action', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get('/admin/reports-analytics')
        ->assertOk()
        ->assertSee('Booking performance trend')
        ->assertSee('data-super-admin-analytics-chart', false)
        ->assertSee('data-super-admin-analytics-layout="compact"', false)
        ->assertSee('dashboard-reveal card-lift flex flex-col shadow-sm', false)
        ->assertSee('h-48', false)
        ->assertSee('data-super-admin-analytics-horizontal-grid', false)
        ->assertSee('data-super-admin-analytics-legend', false)
        ->assertSee('size-1.5 shrink-0 rounded-[1px] bg-blue-500', false)
        ->assertSee('bg-blue-500', false)
        ->assertSee('data-super-admin-analytics-series="active"', false)
        ->assertSee('data-super-admin-analytics-series="completed"', false)
        ->assertSee('data-super-admin-analytics-series="cancelled"', false)
        ->assertSee('data-super-admin-analytics-area="active"', false)
        ->assertSee('data-super-admin-analytics-area="completed"', false)
        ->assertSee('data-super-admin-analytics-area="cancelled"', false)
        ->assertSee('fill-opacity="0.09"', false)
        ->assertSee('stroke-width="2"', false)
        ->assertSee('r="3"', false)
        ->assertSee('Export results')
        ->assertSee('wire:click="exportAnalytics"', false)
        ->assertSee('data-super-admin-section-menu', false);
});

test('super admin utility actions use visible theme-aware buttons', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get('/admin/system-health')
        ->assertOk()
        ->assertSee('Run scheduler')
        ->assertSee('Clear cache')
        ->assertSee('Retry failed jobs')
        ->assertSee('bg-[var(--color-accent)]', false)
        ->assertSee('text-[var(--color-accent-foreground)]', false);
});

test('super admins can download reports analytics as CSV', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'reports-analytics'])
        ->call('exportAnalytics')
        ->assertFileDownloaded('fixtrack-analytics.csv', null, 'text/csv; charset=UTF-8');
});

test('super admin module tables use the reference layout', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get('/admin/service-bookings')
        ->assertOk()
        ->assertSee('super-admin-scroll-table', false)
        ->assertSee('sticky top-0 z-10', false)
        ->assertSee('first:!ps-6 last:!pe-6', false)
        ->assertDontSee('min-w-max', false)
        ->assertSee('w-24 !pe-8', false)
        ->assertSee('group/end-align', false)
        ->assertSee('rounded-xl border border-zinc-200 bg-white', false)
        ->assertSee('Actions')
        ->assertSee('Nothing to show yet');
});

test('super admin module tables wrap long cell values', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => 'A customer name that must wrap instead of overlapping adjacent columns',
    ]);
    DB::table('bookings')->insert([
        'user_id' => $customer->id,
        'reference' => 'FT-WRAP-001',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'scheduled',
        'status' => 'pending',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get('/admin/service-bookings')
        ->assertOk()
        ->assertSee('A customer name that must wrap instead of overlapping adjacent columns')
        ->assertSee('data-app-table-actions-menu', false)
        ->assertSee('whitespace-normal break-words', false);
});

test('the shared table shell is used across admin and technician pages', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-app-table-shell', false)
        ->assertSee('data-super-admin-dashboard-table', false);

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'job-requests']))
        ->assertOk()
        ->assertSee('data-app-table-shell', false)
        ->assertSee('data-flux-table', false);
});

test('super admin module tables declare semantic column types', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get('/admin/technician-verification')
        ->assertOk()
        ->assertSee('data-super-admin-column-type="chips"', false)
        ->assertSee('data-super-admin-column-type="risk"', false)
        ->assertSee('data-super-admin-column-type="status"', false);
});

test('super admin module tables render semantic status badges', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    DB::table('bookings')->insert([
        'user_id' => $superAdmin->id,
        'reference' => 'FT-BADGE-001',
        'customer_name' => 'Badge Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'scheduled',
        'status' => 'completed',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get('/admin/service-bookings')
        ->assertOk()
        ->assertSee('data-super-admin-badge-type="status"', false)
        ->assertSee('data-super-admin-badge-color="emerald"', false)
        ->assertSee('Completed');
});

test('super admin stat cards use semantic tones', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get('/admin/system-health')
        ->assertOk()
        ->assertSee('!border-s-emerald-400', false)
        ->assertSee('!border-s-rose-400', false);
});

test('super admins can update user roles through a module action', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create(['role' => 'customer']);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'users-roles'])
        ->call('updateUserRole', $user->id, 'technician');

    expect($user->refresh()->role)->toBe('technician');
});

test('super admin role changes reject staff roles without a workspace', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($superAdmin)
        ->get('/admin/users-roles')
        ->assertOk()
        ->assertDontSee('Make dispatcher')
        ->assertDontSee('Make support staff')
        ->assertDontSee('Make finance staff');

    foreach (['dispatcher', 'support', 'finance'] as $role) {
        Livewire::actingAs($superAdmin)
            ->test(ModulePage::class, ['module' => 'users-roles'])
            ->call('updateUserRole', $user->id, $role)
            ->assertHasErrors(['value']);
    }

    expect($user->refresh()->role)->toBe('customer');
});

test('super admin actions use a custom confirmation modal instead of native browser dialogs', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($superAdmin)
        ->get('/admin/users-roles')
        ->assertOk()
        ->assertDontSee('wire:confirm', false)
        ->assertSee('data-super-admin-confirmation-modal', false);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'users-roles'])
        ->call('requestConfirmation', 'suspend-user', $user->id)
        ->assertSet('showConfirmation', true)
        ->assertSet('confirmationTitle', 'Suspend this account?')
        ->assertSee('The user will no longer be able to access the platform.')
        ->assertSee('Confirm')
        ->assertSee('Cancel');
});

test('confirmed super admin actions update records and show a success toast', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create(['role' => 'customer']);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'users-roles'])
        ->call('requestConfirmation', 'make-technician', $user->id)
        ->call('executeConfirmedAction')
        ->assertSet('showConfirmation', false)
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return data_get($params, 'dataset.variant') === 'success';
        });

    expect($user->refresh()->role)->toBe('technician');
});

test('failed confirmed super admin actions show an error toast', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'users-roles'])
        ->call('requestConfirmation', 'suspend-user', $superAdmin->id)
        ->call('executeConfirmedAction')
        ->assertSet('showConfirmation', false)
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return data_get($params, 'dataset.variant') === 'danger';
        });
});

test('super admins can update booking status through a module action', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $superAdmin->id,
        'reference' => 'FT-TEST-001',
        'customer_name' => 'Test Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'scheduled',
        'status' => 'pending',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'service-bookings'])
        ->call('updateBookingStatus', $bookingId, 'cancelled', 'Cancelled by admin');

    expect(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('cancelled');
});

test('super admin dispatch connects booking assignment to verified technicians and availability', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'reviewer_id' => $superAdmin->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['aircon']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $superAdmin->id,
        'reference' => 'FT-ASSIGN-001',
        'customer_name' => 'Dispatch Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'dispatch-monitor'])
        ->call('openAssignment', $bookingId)
        ->set('assignmentTechnicianId', (string) $technician->id)
        ->call('saveAssignment');

    expect(DB::table('bookings')->where('id', $bookingId)->value('assigned_technician_id'))->toBe($technician->id)
        ->and(DB::table('bookings')->where('id', $bookingId)->value('status'))->toBe('assigned')
        ->and($technician->refresh()->availability_status)->toBe('busy');

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'service-bookings'])
        ->call('updateBookingStatus', $bookingId, 'completed');

    expect($technician->refresh()->availability_status)->toBe('available');
});

test('super admin dispatch rejects a verified technician without the exact capability', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'account_status' => 'active',
        'availability_status' => 'available',
    ]);
    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'reviewer_id' => $superAdmin->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => json_encode(['electronics-battery']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $superAdmin->id,
        'reference' => 'FT-ASSIGN-LCD',
        'customer_name' => 'Dispatch Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'electronics-lcd',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superAdmin);

    expect(fn () => (new ModulePage)->assignBooking($bookingId, $technician->id))
        ->toThrow(HttpException::class);

    expect(DB::table('bookings')->whereKey($bookingId)->first())
        ->assigned_technician_id->toBeNull()
        ->and($technician->refresh()->availability_status)->toBe('available');
});

test('super admin sees exact technician capability labels', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create(['role' => 'technician']);
    DB::table('technician_verifications')->insert([
        'user_id' => $technician->id,
        'status' => 'submitted',
        'risk_level' => 'low',
        'service_categories' => json_encode(['electronics-lcd', 'electronics-battery']),
        'years_experience' => 3,
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'technician-verification'])
        ->assertSee('LCD / screen replacement')
        ->assertSee('Battery replacement');
});

test('super admin can initialize and update live platform settings', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'platform-settings'])
        ->call('initializePlatformSettings');

    $settingId = DB::table('platform_settings')->where('key', 'booking_auto_match')->value('id');

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'platform-settings'])
        ->call('openSettingEditor', $settingId)
        ->set('settingValue', 'false')
        ->call('saveSetting');

    expect(DB::table('platform_settings')->where('key', 'booking_auto_match')->value('value'))->toBe('false')
        ->and(DB::table('platform_settings')->where('key', 'admin_alert_email')->value('value'))->toBe($superAdmin->email);
});

test('super admin verification decisions preserve a required reason', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $verificationId = DB::table('technician_verifications')->insertGetId([
        'user_id' => $technician->id,
        'status' => 'submitted',
        'risk_level' => 'low',
        'service_categories' => json_encode(['aircon']),
        'years_experience' => 1,
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'technician-verification'])
        ->call('requestConfirmation', 'reject-verification', $verificationId)
        ->set('confirmationReason', 'Required documents were not provided.')
        ->call('executeConfirmedAction');

    expect(DB::table('technician_verifications')->where('id', $verificationId)->value('status'))->toBe('rejected')
        ->and(DB::table('technician_verifications')->where('id', $verificationId)->value('decision_reason'))->toBe('Required documents were not provided.');
});

test('super admin catalog and support actions update their customer-facing records', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $ticketId = DB::table('support_tickets')->insertGetId([
        'reference' => 'SUP-CONNECT-001',
        'user_id' => $superAdmin->id,
        'subject' => 'Booking question',
        'category' => 'booking',
        'priority' => 'normal',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'support-disputes'])
        ->call('openSupportEditor', $ticketId)
        ->set('supportStatus', 'resolved')
        ->set('supportPriority', 'high')
        ->set('supportMessage', 'Your booking question has been resolved.')
        ->call('saveSupportTicket');

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'service-catalog'])
        ->call('openCatalogEditor')
        ->set('catalogCode', 'AC-CONNECT')
        ->set('catalogName', 'Aircon Connection Service')
        ->set('catalogCategory', 'Aircon')
        ->set('catalogBasePrice', '850')
        ->set('catalogDescription', 'Connected catalog service.')
        ->call('saveCatalogService');

    expect(DB::table('support_tickets')->where('id', $ticketId)->value('status'))->toBe('resolved')
        ->and(DB::table('support_tickets')->where('id', $ticketId)->value('latest_message'))->toBe('Your booking question has been resolved.')
        ->and(DB::table('service_catalog')->where('code', 'AC-CONNECT')->value('is_active'))->toBe(1);
});

test('super admins can advance a walk-in entry and update its counter', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $counterId = DB::table('service_counters')->insertGetId([
        'name' => 'Counter 1',
        'status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $entryId = DB::table('walk_in_entries')->insertGetId([
        'queue_number' => 'W-001',
        'customer_name' => 'Walk-in Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'priority' => 'standard',
        'status' => 'waiting',
        'counter_id' => $counterId,
        'checked_in_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'walk-in-queue'])
        ->call('updateWalkInStatus', $entryId, 'serving');

    expect(DB::table('walk_in_entries')->where('id', $entryId)->value('status'))->toBe('serving')
        ->and(DB::table('service_counters')->where('id', $counterId)->value('status'))->toBe('busy');
});

test('super admins cannot reopen a completed walk-in entry', function () {
    $superAdmin = User::factory()->create(['role' => 'superadmin']);
    $entryId = DB::table('walk_in_entries')->insertGetId([
        'queue_number' => 'W-002',
        'customer_name' => 'Completed Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'priority' => 'standard',
        'status' => 'completed',
        'checked_in_at' => now()->subHour(),
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ModulePage::class, ['module' => 'walk-in-queue'])
        ->call('updateWalkInStatus', $entryId, 'serving');

    expect(DB::table('walk_in_entries')->where('id', $entryId)->value('status'))->toBe('completed');
});

test('regular users cannot run super admin module actions', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $target = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user);

    expect(fn () => (new ModulePage)->updateUserRole($target->id, 'technician'))
        ->toThrow(HttpException::class);

    expect($target->refresh()->role)->toBe('customer');
});
