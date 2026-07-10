<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class LogoutRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'refresh_token' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
