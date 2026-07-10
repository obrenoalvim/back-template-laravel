<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin')]
class AdminController extends Controller
{
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'List all users (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'User list'),
            new OA\Response(response: 403, description: 'Admin access required'),
        ],
    )]
    public function users(): JsonResponse
    {
        return response()->json([
            'users' => User::query()->select('id', 'name', 'email', 'role')->get(),
        ]);
    }
}
