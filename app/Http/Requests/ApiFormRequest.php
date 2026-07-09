<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every request-validated API endpoint. Authorization for API
 * routes is handled by middleware (Sanctum guards, policies on the
 * controller action), not per-field here — so authorize() defaults true.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
