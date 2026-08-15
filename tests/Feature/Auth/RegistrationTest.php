<?php

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
