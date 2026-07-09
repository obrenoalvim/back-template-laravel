<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Http\Requests\ApiFormRequest;

class StoreNoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }
}
