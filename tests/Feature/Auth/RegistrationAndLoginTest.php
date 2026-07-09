<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('registers a user, hashes the password, and returns a usable token', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'ana@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $user = User::where('email', 'ana@example.com')->firstOrFail();
    expect($user->password)->not->toBe('password123');

    $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
        ->getJson('/api/account/')
        ->assertOk()
        ->assertJsonPath('user.email', 'ana@example.com');
});

it('rejects a duplicate email on register with a validation error', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Someone',
        'email' => 'dup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.email.0', 'The email has already been taken.');
});

it('rejects login with wrong credentials and accepts the right ones', function () {
    User::factory()->create([
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'wrong',
    ])->assertStatus(401)->assertJsonPath('error.message', 'Invalid credentials.');

    $this->postJson('/api/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
    ])->assertOk()->assertJsonStructure(['user', 'token']);
});

it('revokes the token on logout so it can no longer authenticate', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/auth/logout')
        ->assertOk();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/account/')
        ->assertStatus(401);
});

it('returns 401 (not 500) for an unauthenticated request without an Accept header', function () {
    // Regression test: Laravel's Authenticate middleware calls route('login')
    // to build the redirect target, which used to throw RouteNotFoundException
    // (surfaced as a 500) because this API-only app had no such route.
    $response = $this->call('POST', '/api/auth/logout');

    $response->assertStatus(401);
});
