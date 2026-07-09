<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
