<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Http\Requests\ApiFormRequest;

class UpdateNoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }
}
