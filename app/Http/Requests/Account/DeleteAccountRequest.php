<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class DeleteAccountRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
        ];
    }
}
