<?php

declare(strict_types=1);

use App\Models\User;

it('changes the password, keeps the acting token alive, and revokes the rest', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $acting = $user->createToken('acting')->plainTextToken;
    $other = $user->createToken('other')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $acting")
        ->putJson('/api/account/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);

    $this->withHeader('Authorization', "Bearer $acting")
        ->putJson('/api/account/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer $acting")->getJson('/api/account/')->assertOk();
    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer $other")->getJson('/api/account/')->assertStatus(401);
});

it('deletes the account after password confirmation and revokes access', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->deleteJson('/api/account/', ['password' => 'wrong'])
        ->assertStatus(422);

    $this->withHeader('Authorization', "Bearer $token")
        ->deleteJson('/api/account/', ['password' => 'password123'])
        ->assertOk();

    expect(User::find($user->id))->toBeNull();
    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer $token")->getJson('/api/account/')->assertStatus(401);
});
