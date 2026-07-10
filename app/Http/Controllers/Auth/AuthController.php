<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth')]
class AuthController extends Controller
{
    /**
     * @return array{accessToken: string, refreshToken: string}
     */
    private function issueTokenPair(User $user): array
    {
        $access = $user->createToken(
            'access',
            ['access'],
            now()->addMinutes(config('sanctum.access_ttl_minutes')),
        );
        $refresh = $user->createToken(
            'refresh',
            ['refresh'],
            now()->addDays(config('sanctum.refresh_ttl_days')),
        );

        return [
            'accessToken' => $access->plainTextToken,
            'refreshToken' => $refresh->plainTextToken,
        ];
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new account',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ],
            ),
        ),
        responses: [new OA\Response(response: 201, description: 'Account created, token pair issued')],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        event(new Registered($user));

        return response()->json([
            'user' => new UserResource($user),
            ...$this->issueTokenPair($user),
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Log in with email and password',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated, token pair issued'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            abort(401, 'Invalid credentials.');
        }

        return response()->json([
            'user' => new UserResource($user),
            ...$this->issueTokenPair($user),
        ]);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Exchange a refresh token for a new token pair',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'New token pair issued (old refresh token revoked)'),
            new OA\Response(response: 403, description: 'Token presented is not a refresh token'),
        ],
    )]
    public function refresh(Request $request): JsonResponse
    {
        // The presented Bearer token IS the credential here (auth:sanctum resolves
        // $request->user() from it) — reject outright if it's an access token, not
        // the refresh one, so access tokens can't be used to keep minting new pairs.
        $user = $request->user();
        if (! $user->tokenCan('refresh')) {
            abort(403, 'This endpoint requires a refresh token.');
        }

        $user->currentAccessToken()->delete();

        return response()->json($this->issueTokenPair($user));
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Log out (revoke the current token and, if provided, its paired refresh token)',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'refresh_token',
                        type: 'string',
                        nullable: true,
                        description: 'Also revoke this refresh token, so logout leaves nothing behind that could still mint new access tokens.',
                    ),
                ],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Logged out')],
    )]
    public function logout(LogoutRequest $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        if ($request->validated('refresh_token')) {
            PersonalAccessToken::findToken($request->validated('refresh_token'))?->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    #[OA\Get(
        path: '/api/auth/verify-email/{id}/{hash}',
        summary: 'Verify an email address via signed link',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Email verified'),
            new OA\Response(response: 403, description: 'Invalid verification link'),
        ],
    )]
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json(['message' => 'Email verified.']);
    }

    #[OA\Post(
        path: '/api/auth/email/resend',
        summary: 'Resend the email verification link',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Verification link sent (or already verified)')],
    )]
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    #[OA\Post(
        path: '/api/auth/forgot-password',
        summary: 'Request a password reset email',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [new OA\Property(property: 'email', type: 'string', format: 'email')],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Reset link sent if the email exists')],
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Same response whether or not the email exists — no enumeration.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    #[OA\Post(
        path: '/api/auth/reset-password',
        summary: 'Reset password with a token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset'),
            new OA\Response(response: 400, description: 'Invalid or expired token'),
        ],
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            abort(400, __($status));
        }

        return response()->json(['message' => 'Password reset.']);
    }
}
