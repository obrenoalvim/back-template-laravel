<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        // Revoke every other session/device; keep the one making this request alive.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed.']);
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
}
