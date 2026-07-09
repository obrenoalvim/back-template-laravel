<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class EnvValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $required = config('env.required', []);

        $missing = array_filter(
            $required,
            fn (string $key) => env($key) === null || env($key) === '',
        );

        if ($missing !== []) {
            throw new RuntimeException(
                'Missing required env var(s): '.implode(', ', $missing).
                '. Copy .env.example to .env and fill them in.',
            );
        }
    }
}
