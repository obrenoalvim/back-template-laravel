<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests hit real Postgres (RefreshDatabase migrates a clean schema
| per test) — no DB mocking. Unit tests never touch the database.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| AuthManager caches resolved guard instances (and RequestGuard caches the
| resolved user on itself) for the app's lifetime — fine in production
| where every real request boots a fresh app, but within one test making
| several sequential requests through the same booted app, a token
| revoked by request 2 would still "authenticate" on request 3 without
| this. Call it after any request that changes auth state.
|
*/

function forgetAuthGuards(): void
{
    app('auth')->forgetGuards();
}
