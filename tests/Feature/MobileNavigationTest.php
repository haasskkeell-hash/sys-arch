<?php

use App\Livewire\Customer\ModulePage;
use App\Models\ServiceCatalog;
use App\Models\TechnicianVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('authenticated workspaces expose mobile bottom navigation and keep the sidebar desktop-only', function () {
    foreach ([
        ['customer', 'customer.module', ['home', 'activity', 'messages', 'account']],
        ['technician', 'technician.module', ['dashboard', 'job-requests', 'my-jobs', 'dispatch-routes', 'verification-profile']],
        ['superadmin', 'admin.dashboard', ['dashboard', 'service-bookings', 'dispatch-monitor', 'users-roles']],
    ] as [$role, $routeName, $primaryItems]) {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get(route($routeName));

        $response->assertOk()
            ->assertSee('data-app-mobile-navigation', false)
            ->assertSee('data-app-desktop-sidebar', false)
            ->assertSee('max-lg:hidden', false);

        foreach ($primaryItems as $item) {
            $response->assertSee('data-app-mobile-nav-item="'.$item.'"', false);
        }

        if ($role === 'customer') {
            $response->assertSee('data-app-customer-mobile-shell', false)
                ->assertSee('max-lg:!px-0', false)
                ->assertSee('max-lg:!pt-0', false)
                ->assertDontSee('data-app-mobile-nav-item="more"', false);
        } elseif ($role === 'technician') {
            $response->assertDontSee('data-app-mobile-nav-item="more"', false)
                ->assertDontSee('max-lg:!px-0', false)
                ->assertDontSee('data-app-customer-mobile-shell', false);
        } else {
            $response->assertSee('data-app-mobile-nav-item="more"', false)
                ->assertDontSee('max-lg:!px-0', false)
                ->assertDontSee('data-app-customer-mobile-shell', false);
        }
    }

});

test('fixed mobile home shells stay hidden at the desktop breakpoint', function () {
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($styles)->toContain(<<<'CSS'
@media (min-width: 64rem) {
    .customer-mobile-shell--fixed-home,
    .technician-mobile-shell--fixed-home {
        display: none;
    }
}
CSS);
});

test('customer and technician cancellation controls use solid red treatment', function () {
    foreach (['customer', 'technician'] as $role) {
        $mobileShell = file_get_contents(resource_path("views/livewire/{$role}/mobile-shell.blade.php"));
        $desktopModule = file_get_contents(resource_path("views/livewire/{$role}/module-page.blade.php"));

        expect($mobileShell)
            ->toContain('bg-red-500 px-3 text-xs font-semibold text-white')
            ->not->toContain('border-red-200 px-3 text-xs font-semibold text-red-700');
        expect($desktopModule)->toContain('variant="danger"');
    }
});

test('technician profile exposes all secondary modules and logout', function () {
    $technician = User::factory()->create(['role' => 'technician']);

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
        ->get(route('technician.module', ['module' => 'verification-profile']))
        ->assertOk()
        ->assertSee('data-app-mobile-nav-item="verification-profile"', false)
        ->assertSee('M17.982 18.725', false)
        ->assertDontSee('data-app-mobile-nav-item="more"', false)
        ->assertSee('data-app-technician-profile-logout', false)
        ->assertSee('Log out')
        ->assertSee('My account')
        ->assertSee('General')
        ->assertSee('Verification details');

    foreach (['schedule', 'earnings', 'ratings-reviews', 'verification-profile', 'notifications', 'support'] as $module) {
        $this->actingAs($technician)
            ->get(route('technician.module', ['module' => 'verification-profile']))
            ->assertSee('data-app-technician-profile-module="'.$module.'"', false);
    }
});

test('customer modules resolve into the four mobile workspace tabs', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    foreach ([
        [null, 'home', 'Quick book'],
        ['my-bookings', 'activity', 'Activity'],
        ['notifications', 'messages', 'Messages'],
        ['settings', 'account', 'My account'],
    ] as [$module, $tab, $content]) {
        $route = $module === null
            ? route('customer.module')
            : route('customer.module', ['module' => $module]);

        $this->actingAs($customer)
            ->get($route)
            ->assertOk()
            ->assertSee('data-app-customer-mobile-tab="'.$tab.'"', false)
            ->assertSee($content);
    }
});

test('customer home renders the interactive map surface and location control', function () {
    $customer = User::factory()->create([
        'role' => 'customer',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
    ]);

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertOk()
        ->assertSee('data-app-customer-map', false)
        ->assertSee('data-map-center-lat="14.5995"', false)
        ->assertSee('data-map-center-lng="120.9842"', false)
        ->assertSee('data-app-map-locate', false)
        ->assertSee('Loading live map', false)
        ->assertSee('customer-mobile-shell--fixed-home', false)
        ->assertSee('lg:hidden', false);
});

test('mobile maps use distinct role marker treatments', function () {
    $markerScript = file_get_contents(resource_path('js/customer-map.js'));
    $mapStyles = file_get_contents(resource_path('css/app.css'));

    expect($markerScript)
        ->toContain('customer-map-marker__pin')
        ->toContain('customer-map-marker__icon')
        ->toContain('customer-map-marker__portrait')
        ->toContain('customer-map-marker__initials')
        ->toContain('marker.avatar')
        ->toContain('customer-map-marker--technician')
        ->toContain('customer-map-marker--active')
        ->toContain('updateLocationFromCoordinates')
        ->toContain("on('dragend'")
        ->and($mapStyles)
        ->toContain('.customer-map-marker__pin')
        ->toContain('.customer-map-marker__portrait')
        ->toContain('@keyframes customer-map-marker-pulse');
});

test('customer and technician profiles expose editable profile photos', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'settings']))
        ->assertOk()
        ->assertSee('data-profile-photo-input', false)
        ->assertSee('data-profile-photo-trigger', false)
        ->assertSee('Click to change photo');

    $this->actingAs($technician)
        ->get(route('technician.module', ['module' => 'verification-profile']))
        ->assertOk()
        ->assertSee('data-profile-photo-input', false)
        ->assertSee('data-profile-photo-trigger', false)
        ->assertSee('Click to change photo');
});

test('profile photo editor is inline instead of a separate card', function () {
    $editor = file_get_contents(resource_path('views/components/profile-photo-editor.blade.php'));

    expect($editor)
        ->toContain('data-profile-photo-inline')
        ->toContain('data-profile-photo-trigger')
        ->not->toContain('border-zinc-200 bg-white');
});

test('customer and technician map markers include profile photo data', function () {
    $customer = User::factory()->create([
        'role' => 'customer',
        'avatar_path' => 'avatars/customer.png',
    ]);
    $technician = User::factory()->create([
        'role' => 'technician',
        'avatar_path' => 'avatars/technician.png',
        'latitude' => 14.6000000,
        'longitude' => 120.9850000,
    ]);

    DB::table('bookings')->insert([
        'user_id' => $customer->id,
        'assigned_technician_id' => $technician->id,
        'reference' => 'FX-PHOTO-MARKER-01',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'quick',
        'status' => 'en_route',
        'address' => 'Quezon City',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($customer->avatarUrl())->toBe('/storage/avatars/customer.png');
    expect($technician->avatarUrl())->toBe('/storage/avatars/technician.png');

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertOk()
        ->assertSee('storage\\/avatars\\/customer.png', false)
        ->assertSee('storage\\/avatars\\/technician.png', false);

    $this->actingAs($technician)
        ->get(route('technician.module'))
        ->assertOk()
        ->assertSee('storage\\/avatars\\/customer.png', false)
        ->assertSee('storage\\/avatars\\/technician.png', false);
});

test('customer home map includes recent places and their assigned technicians', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Map Technician',
        'latitude' => 14.6100000,
        'longitude' => 120.9950000,
    ]);

    DB::table('bookings')->insert([
        'user_id' => $customer->id,
        'assigned_technician_id' => $technician->id,
        'reference' => 'FX-HOME-MAP-01',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => 'completed',
        'address' => 'Recent service location',
        'latitude' => 14.5995000,
        'longitude' => 120.9842000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertOk()
        ->assertSee('&quot;type&quot;:&quot;booking&quot;', false)
        ->assertSee('Recent service location', false)
        ->assertSee('&quot;type&quot;:&quot;technician&quot;', false)
        ->assertSee('Map Technician', false);
});

test('customer home exposes active booking tracking and waiting state', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    DB::table('bookings')->insert([
        'user_id' => $customer->id,
        'reference' => 'FX-TRACKING-01',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000000',
        'service_type' => 'aircon',
        'booking_type' => 'quick',
        'status' => 'matching',
        'address' => 'Quezon City',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertOk()
        ->assertSee('data-realtime-tracking', false)
        ->assertSee('data-realtime-tracking-interval="1000"', false)
        ->assertSee('Waiting for a technician');
});

test('customer home opens the approved mobile booking flows', function () {
    $customer = User::factory()->create(['role' => 'customer', 'phone' => '09171234567']);
    $technician = User::factory()->create(['role' => 'technician', 'account_status' => 'active']);
    TechnicianVerification::create([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => ['aircon'],
        'address' => 'FixTrack Service Store, Quezon City',
    ]);
    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
        'description' => 'Keep your unit running clean.',
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->assertSet('mobileHomeView', 'home')
        ->assertSee('Quick book')
        ->call('openMobileQuickBook')
        ->assertSet('mobileHomeView', 'quick-book')
        ->assertSee('data-map-auto-locate="true"', false)
        ->assertSee('customer-mobile-booking-map', false)
        ->assertSee('data-map-draggable="true"', false)
        ->assertSee('Address')
        ->assertSee('Mobile number')
        ->assertSee('x-on:input.debounce.600ms', false)
        ->assertSee('Description')
        ->set('addressLatitude', 14.5995)
        ->set('addressLongitude', 120.9842)
        ->assertSee('customer-mobile-booking-map', false)
        ->assertSee('data-map-draggable="true"', false)
        ->call('openMobileSchedule')
        ->assertSet('mobileHomeView', 'schedule')
        ->assertSee('Search technician stores')
        ->assertSee($technician->name.' Service Store')
        ->assertSee('FixTrack Service Store, Quezon City')
        ->assertDontSee('Available FixTrack services near you.')
        ->set('mobileTechnicianStoreSearch', 'Quezon City')
        ->assertSee($technician->name.' Service Store')
        ->set('mobileTechnicianStoreSearch', 'no-such-store')
        ->assertSee('No matching technician stores')
        ->set('mobileTechnicianStoreSearch', '')
        ->call('selectMobileTechnicianStore', $technician->id)
        ->assertSet('mobileHomeView', 'schedule-form')
        ->assertSet('selectedTechnicianId', $technician->id)
        ->assertSet('bookingType', 'scheduled')
        ->assertSee('Choose a service')
        ->assertSee('Preferred date and time')
        ->call('openMobileNavigate')
        ->assertSet('mobileHomeView', 'navigate')
        ->assertSee('Recent')
        ->assertSee('x-on:input.debounce.600ms="$wire.searchAddress($event.target.value)"', false)
        ->call('setMobileNavigateTab', 'saved')
        ->assertSee('No saved places');
});

test('scheduled booking targets the selected technician store', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'account_status' => 'active']);
    TechnicianVerification::create([
        'user_id' => $technician->id,
        'status' => 'approved',
        'risk_level' => 'low',
        'service_categories' => ['aircon'],
        'address' => 'FixTrack Service Store, Manila',
    ]);
    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('openMobileSchedule')
        ->call('selectMobileTechnicianStore', $technician->id)
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'aircon')
        ->set('address', 'Quezon City')
        ->set('scheduledAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('createBooking')
        ->assertSet('mobileHomeView', 'home');

    expect(DB::table('bookings')->where('user_id', $customer->id)->first())
        ->assigned_technician_id->toBe($technician->id)
        ->status->toBe('assigned')
        ->booking_type->toBe('scheduled');
});

test('mobile booking returns to home with its active waiting state', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('openMobileQuickBook')
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'aircon')
        ->set('address', 'Quezon City')
        ->call('createBooking')
        ->assertSet('moduleSlug', 'overview')
        ->assertSet('mobileHomeView', 'home')
        ->assertSee('Waiting for a technician');
});
