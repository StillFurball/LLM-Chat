<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('login returns a bearer token for valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('api.login'), ['email' => $user->email, 'password' => 'password']);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type'])
        ->assertJsonPath('token_type', 'Bearer');
    expect(PersonalAccessToken::findToken($response->json('access_token'))?->tokenable_id)->toBe($user->id);
});

test('login returns 401 for a wrong password', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('api.login'), ['email' => $user->email, 'password' => 'wrong-password']);

    $response->assertUnauthorized()
        ->assertExactJson(['status' => 'error', 'message' => 'Email atau password salah.']);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('login returns 422 when email and password are missing', function () {
    $response = $this->postJson(route('api.login'), []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'email' => 'The email field is required.',
        'password' => 'The password field is required.',
    ]);
});

test('login returns 429 after five attempts within a minute', function () {
    $user = User::factory()->create();
    $credentials = ['email' => $user->email, 'password' => 'wrong-password'];

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.login'), $credentials)->assertUnauthorized();
    }

    $this->postJson(route('api.login'), $credentials)->assertTooManyRequests();
});
