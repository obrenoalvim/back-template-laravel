<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Normalizes every API exception into one shape:
 * {"error": {"code": string, "message": string, "details"?: object}}
 */
class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): JsonResponse
    {
        // Laravel's own Handler::prepareException() runs before this callback
        // and rewraps ModelNotFoundException/AuthorizationException into a
        // generic Http(Access)DeniedException with the original as $previous
        // — so `instanceof ModelNotFoundException` here is never true. Check
        // the previous exception too, or these branches are silently dead
        // and the raw Eloquent message (e.g. model class + id) leaks instead.
        $previous = $e->getPrevious();

        [$status, $code, $message, $details] = match (true) {
            $e instanceof ValidationException => [
                422, 'validation_error', 'The given data was invalid.', $e->errors(),
            ],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.', null],
            $e instanceof AuthorizationException || $previous instanceof AuthorizationException => [
                403, 'forbidden', 'This action is unauthorized.', null,
            ],
            $e instanceof ModelNotFoundException || $previous instanceof ModelNotFoundException => [
                404, 'not_found', 'Resource not found.', null,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(), $this->codeForStatus($e->getStatusCode()), $this->safeMessage($e), null,
            ],
            default => [500, 'internal_error', $this->serverErrorMessage($e), null],
        };

        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            404 => 'not_found',
            405 => 'method_not_allowed',
            429 => 'too_many_requests',
            default => 'http_error',
        };
    }

    private function safeMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        return $message !== '' ? $message : class_basename($e);
    }

    /**
     * Never leak internal exception messages for unexpected 500s unless
     * APP_DEBUG is on — those are deliberately-thrown HttpExceptions'
     * messages (handled above) vs. genuine bugs/stack traces here.
     */
    private function serverErrorMessage(Throwable $e): string
    {
        return config('app.debug') === true
            ? $this->safeMessage($e)
            : 'Something went wrong.';
    }
}
