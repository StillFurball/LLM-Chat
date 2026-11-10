<?php

use Database\Seeders\DatabaseSeeder;

test('login page offers the demo account in the local environment', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $response = $this->get(route('login'));

    $response->assertOk()->assertSee('Log in with the demo account');
});

test('login page hides the demo account outside the local environment', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $response = $this->get(route('login'));

    $response->assertOk()->assertDontSee('Log in with the demo account');
});

test('the demo account credentials log in to a seeded database', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->post(route('login.store'), ['email' => 'test@example.com', 'password' => 'password']);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
});
