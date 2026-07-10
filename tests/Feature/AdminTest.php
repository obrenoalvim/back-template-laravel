<?php

declare(strict_types=1);

use App\Models\User;

it('forbids a regular user from listing users', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/admin/users')
        ->assertStatus(403);
});

it('allows an admin to list users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = $admin->createToken('api')->plainTextToken;
    User::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/admin/users')
        ->assertOk();

    expect($response->json('users'))->toHaveCount(2);
});
