<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Account')]
class AccountController extends Controller
{
    #[OA\Get(
        path: '/api/account',
        summary: 'Get the current authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Account'],
        responses: [new OA\Response(response: 200, description: 'Current user')],
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    #[OA\Put(
        path: '/api/account/password',
        summary: 'Change the current user password',
        security: [['bearerAuth' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Password changed')],
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        // Revoke every other session/device; keep the one making this request alive.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed.']);
    }

    #[OA\Delete(
        path: '/api/account',
        summary: 'Delete the current user account',
        security: [['bearerAuth' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [new OA\Property(property: 'password', type: 'string')],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Account deleted')],
    )]
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
}
