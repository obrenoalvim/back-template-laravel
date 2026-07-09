<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;

it('gives the same response for a known and an unknown email (no enumeration)', function () {
    User::factory()->create(['email' => 'known@example.com']);

    $known = $this->postJson('/api/auth/forgot-password', ['email' => 'known@example.com']);
    $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'unknown@example.com']);

    expect($known->json('message'))->toBe($unknown->json('message'));
    $known->assertOk();
    $unknown->assertOk();
});

it('resets the password with a valid token, revokes old tokens, and rejects replay', function () {
    $user = User::factory()->create(['password' => 'oldpassword123']);
    $oldToken = $user->createToken('api')->plainTextToken;

    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => 'garbage',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertStatus(400);

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk()->assertJsonPath('message', 'Password reset.');

    // old token revoked
    $this->withHeader('Authorization', "Bearer $oldToken")
        ->getJson('/api/account/')
        ->assertStatus(401);

    // old password no longer works, new one does
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'oldpassword123'])
        ->assertStatus(401);
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'newpassword123'])
        ->assertOk();

    // token was consumed — replay fails
    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'thirdpassword123',
        'password_confirmation' => 'thirdpassword123',
    ])->assertStatus(400);
});
