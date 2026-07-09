<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'up';
        } catch (\Throwable) {
            $database = 'down';
        }

        return response()->json(
            ['status' => $database === 'up' ? 'ok' : 'degraded', 'database' => $database],
            $database === 'up' ? 200 : 503,
        );
    }
}
