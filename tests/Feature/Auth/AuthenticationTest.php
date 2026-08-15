<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('auth-success')
        ->assertSee('fixtrack-logo.png')
        ->assertSee('auth-icon-button')
        ->assertSee('auth-google-button')
        ->assertSee('Continue with Google')
        ->assertSee(route('auth.google.redirect'));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('google sign in requires configured credentials', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('existing users can sign in with google', function () {
    $user = User::factory()->create(['email' => 'google@example.com']);

    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => route('auth.google.callback'),
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-access-token',
        ]),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-sub-123',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
        ]),
    ]);

    $response = $this->withSession(['google_oauth_state' => 'state-123'])
        ->get(route('auth.google.callback', [
            'code' => 'authorization-code',
            'state' => 'state-123',
        ]));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-sub-123');
});

test('google sign in keeps two factor authentication enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create(['email' => 'google-2fa@example.com']);

    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => route('auth.google.callback'),
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-sub-2fa',
            'email' => 'google-2fa@example.com',
            'email_verified' => true,
        ]),
    ]);

    $response = $this->withSession(['google_oauth_state' => 'state-2fa'])
        ->get(route('auth.google.callback', [
            'code' => 'authorization-code',
            'state' => 'state-2fa',
        ]));

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

test('successful JSON login response includes the success state signal', function () {
    $user = User::factory()->create();

    $response = $this->withHeader('Accept', 'application/json')->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()->assertJson(['two_factor' => false]);
    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
