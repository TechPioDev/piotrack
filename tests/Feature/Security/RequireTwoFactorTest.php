<?php

use App\Models\User;

/**
 * AUTH-004 — enforced two-factor authentication.
 *
 * The dangerous failure here is not under-enforcement, it is a redirect loop:
 * sending a user without 2FA to the setup page while also blocking the setup
 * page locks every account out of the application with no way back in. These
 * tests pin both halves — that ordinary pages are blocked, and that the routes
 * needed to actually enrol (and to log out) stay reachable.
 */
beforeEach(function () {
    config()->set('security.require_two_factor', true);
});

it('is inert until switched on', function () {
    config()->set('security.require_two_factor', false);

    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => null]))
        ->get('/settings/profile')
        ->assertOk();
});

it('sends an account without two-factor to the setup page', function () {
    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => null]))
        ->get('/settings/profile')
        ->assertRedirect(route('two-factor.show'));
});

it('lets an enrolled account through', function () {
    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => now()]))
        ->get('/settings/profile')
        ->assertOk();
});

it('treats a started-but-unconfirmed enrolment as not enrolled', function () {
    // A secret alone means setup was begun and abandoned; only the confirmation
    // timestamp proves the user can actually produce a code.
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('SECRETSECRETSECR'),
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($user)->get('/settings/profile')
        ->assertRedirect(route('two-factor.show'));
});

it('does not trap the user: enrolment and logout stay reachable', function (string $method, string $uri) {
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);

    $response = $this->actingAs($user)->{$method}($uri);

    // Anything but a bounce back to the setup page: reaching the page, being
    // asked to confirm a password first, or a validation error are all fine.
    // A redirect to two-factor.show from these routes is the lockout bug.
    expect($response->headers->get('Location'))->not->toBe(route('two-factor.show'));
})->with([
    'the setup page' => ['get', '/settings/two-factor'],
    'password confirmation guarding it' => ['get', '/confirm-password'],
    'logging out' => ['post', '/logout'],
]);

it('refuses a json caller rather than redirecting it into html', function () {
    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => null]))
        ->getJson('/settings/profile')
        ->assertForbidden();
});

it('leaves token-authenticated api routes alone', function () {
    // The middleware is registered in the web group by design: API tokens are a
    // separate, separately revocable credential, and enforcing 2FA on them would
    // break every integration the moment the flag is switched on.
    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => null]), 'sanctum')
        ->getJson('/api/v1/contacts')
        ->assertStatus(400);
});

it('leaves guests to the login flow', function () {
    $this->get('/login')->assertOk();
});
