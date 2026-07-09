<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/**
 * Live reference of every /api/* endpoint — not meant for production
 * traffic, just a convenience for exploring the template.
 */
class RouteListController extends Controller
{
    public function index(): JsonResponse
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->map(fn ($route) => [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => '/'.$route->uri(),
                'name' => $route->getName(),
            ])
            ->values();

        return response()->json(['routes' => $routes]);
    }
}
