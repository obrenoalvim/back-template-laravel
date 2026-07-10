<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Health')]
class HealthController extends Controller
{
    #[OA\Get(
        path: '/api/health',
        summary: 'Check API and database health',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Healthy'),
            new OA\Response(response: 503, description: 'Database unreachable'),
        ],
    )]
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
