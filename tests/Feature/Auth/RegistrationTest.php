<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee('Customer')
        ->assertSee('Technician')
        ->assertSee('fixtrack-logo.png');
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'customer',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    expect(auth()->user()->role)->toBe('customer');
});

test('technician registration creates a submitted verification record', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Alex Technician',
        'email' => 'technician@example.com',
        'role' => 'technician',
        'phone' => '09171234567',
        'service_category' => 'plumbing',
        'service_area' => 'Quezon City',
        'years_experience' => 5,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $technician = auth()->user();

    expect($technician->role)->toBe('technician')
        ->and($technician->technicianVerification()->first())
        ->status->toBe('submitted')
        ->and($technician->technicianVerification->service_categories)->toBe(['plumbing'])
        ->and($technician->technicianVerification->service_area)->toBe('Quezon City');
});

test('technician registration follows active service catalog categories', function () {
    ServiceCatalog::create([
        'code' => 'solar',
        'name' => 'Solar panel repair',
        'category' => 'Renewable energy',
        'is_active' => true,
    ]);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Electrical repair')
        ->assertSee('Solar panel repair');

    $response = $this->post(route('register.store'), [
        'name' => 'Solar Technician',
        'email' => 'solar-technician@example.com',
        'role' => 'technician',
        'phone' => '09171234567',
        'service_category' => 'solar',
        'service_area' => 'Quezon City',
        'years_experience' => 5,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(auth()->user()->technicianVerification->service_categories)->toBe(['solar']);
});

test('technician registration stores multiple exact service capabilities', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Electronics Technician',
        'email' => 'electronics-technician@example.com',
        'role' => 'technician',
        'phone' => '09171234567',
        'service_categories' => ['electronics-lcd', 'electronics-battery'],
        'service_area' => 'Quezon City',
        'years_experience' => 5,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(auth()->user()->technicianVerification->service_categories)
        ->toBe(['electronics-lcd', 'electronics-battery']);
});

test('technician registration requires at least one exact service capability', function () {
    $response = $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Unskilled Technician',
            'email' => 'unskilled-technician@example.com',
            'role' => 'technician',
            'phone' => '09171234567',
            'service_categories' => [],
            'service_area' => 'Quezon City',
            'years_experience' => 5,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirect(route('register'))
        ->assertSessionHasErrors('service_categories');

    $this->assertGuest();
});

test('technician registration rolls back the user when verification creation fails', function () {
    Event::listen('eloquent.creating: App\\Models\\TechnicianVerification', function (): void {
        throw new RuntimeException('Verification storage unavailable.');
    });

    try {
        expect(fn () => app(CreateNewUser::class)->create([
            'name' => 'Rollback Technician',
            'email' => 'rollback-technician@example.com',
            'role' => 'technician',
            'phone' => '09171234567',
            'service_category' => 'plumbing',
            'service_area' => 'Quezon City',
            'years_experience' => 5,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))->toThrow(RuntimeException::class, 'Verification storage unavailable.');
    } finally {
        Event::forget('eloquent.creating: App\\Models\\TechnicianVerification');
    }

    expect(User::query()->where('email', 'rollback-technician@example.com')->exists())->toBeFalse();
});

test('registration cannot self assign an admin role', function () {
    $response = $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Attempted Admin',
            'email' => 'admin-signup@example.com',
            'role' => 'superadmin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirect(route('register'))
        ->assertSessionHasErrors('role');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'admin-signup@example.com']);
});
