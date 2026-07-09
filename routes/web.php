<?php

use Illuminate\Support\Facades\Route;

/*
 * API-only app — no Blade views. This route exists only because Laravel's
 * auth middleware calls route('login') to build AuthenticationException's
 * redirectTo when a guest's request doesn't send Accept: application/json
 * (curl, browsers, etc). Without it that route() call throws
 * RouteNotFoundException before the 401 is ever raised, turning every
 * unauthenticated request into a 500. JSON clients never actually see this
 * route — shouldRenderJsonWhen() renders the 401 via ApiExceptionRenderer
 * before any redirect would happen.
 */
Route::get('/login', fn () => abort(401, 'Unauthenticated.'))->name('login');
