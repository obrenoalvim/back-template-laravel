<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('verifies email with a valid signed link and rejects a tampered one', function () {
    $user = User::factory()->unverified()->create();

    $validUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $tampered = $validUrl.'x';

    $this->getJson($tampered)->assertStatus(403);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->getJson($validUrl)->assertOk()->assertJsonPath('message', 'Email verified.');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // replay is idempotent, not an error
    $this->getJson($validUrl)->assertOk()->assertJsonPath('message', 'Email already verified.');
});

it('resends the verification notification only while unverified', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/auth/email/resend')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});
