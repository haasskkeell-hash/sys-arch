<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;

test('customer can access workspace after login', function () {
    $user = User::factory()->create([
        'role' => 'customer',
        'email_verified_at' => null,
    ]);

    // Use actingAs to bypass login flow
    $this->actingAs($user);

    // Try to access dashboard
    $dashboardResponse = $this->get(route('dashboard'));
    dump('Dashboard status: '.$dashboardResponse->getStatusCode());

    // Try to access customer module directly
    $customerResponse = $this->get(route('customer.module'));
    dump('Customer module status: '.$customerResponse->getStatusCode());

    if ($customerResponse->getStatusCode() === 403) {
        dump('GOT 403!');
        dump('Body: '.substr($customerResponse->getContent(), 0, 1000));
    }

    $this->assertEquals(200, $customerResponse->getStatusCode());
});

test('customer with email_verified_at null and MustVerifyEmail can access workspace', function () {
    // This tests whether the verified middleware blocks unverified users
    $user = User::factory()->create([
        'role' => 'customer',
        'email_verified_at' => null,
    ]);

    $this->actingAs($user);

    // Check if the model implements MustVerifyEmail
    dump('Implements MustVerifyEmail: '.($user instanceof MustVerifyEmail ? 'yes' : 'no'));
    dump('email_verified_at: '.($user->email_verified_at ? 'yes' : 'no'));

    $response = $this->get(route('customer.module'));
    dump('Status: '.$response->getStatusCode());

    $this->assertEquals(200, $response->getStatusCode());
});
