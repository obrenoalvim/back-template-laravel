<?php

declare(strict_types=1);

it('lists every /api route', function () {
    $this->getJson('/api/routes')
        ->assertOk()
        ->assertJsonFragment(['method' => 'GET', 'uri' => '/api/health'])
        ->assertJsonFragment(['method' => 'GET', 'uri' => '/api/routes']);
});
