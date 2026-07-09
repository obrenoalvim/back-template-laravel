<?php

declare(strict_types=1);

it('reports ok when the database is reachable', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'database' => 'up']);
});
