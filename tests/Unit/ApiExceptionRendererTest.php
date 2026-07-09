<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Models\Note;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Instantiated directly, no HTTP kernel/DB involved — a real unit test.
it('shapes a validation exception into the shared error envelope', function () {
    $errors = validator([], ['email' => 'required'])->errors();
    $exception = ValidationException::withMessages($errors->toArray());

    $response = (new ApiExceptionRenderer)->render($exception, Request::create('/api/x'));
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(422)
        ->and($data['error']['code'])->toBe('validation_error')
        ->and($data['error']['details'])->toHaveKey('email');
});

it('never leaks the internal message of an unexpected 500 when debug is off', function () {
    config(['app.debug' => false]);

    $response = (new ApiExceptionRenderer)->render(
        new RuntimeException('leaked db credentials in here'),
        Request::create('/api/x'),
    );
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(500)
        ->and($data['error']['message'])->toBe('Something went wrong.')
        ->and($data['error']['message'])->not->toContain('leaked');
});

it('generalizes a wrapped ModelNotFoundException instead of leaking the Eloquent message', function () {
    $modelNotFound = new ModelNotFoundException;
    $modelNotFound->setModel(Note::class, [99999]);
    $wrapped = new NotFoundHttpException($modelNotFound->getMessage(), $modelNotFound);

    $response = (new ApiExceptionRenderer)->render($wrapped, Request::create('/api/x'));
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(404)
        ->and($data['error']['message'])->toBe('Resource not found.');
});
