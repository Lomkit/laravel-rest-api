<?php

namespace Lomkit\Rest\Http\Requests\Traits;

use Illuminate\Auth\Access\Response;

trait InteractsWithRules
{
    public function authorize(): bool|Response
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
