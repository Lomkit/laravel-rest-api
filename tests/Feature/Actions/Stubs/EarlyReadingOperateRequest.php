<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Validation\Validator;
use Lomkit\Rest\Http\Requests\OperateRequest;

class EarlyReadingOperateRequest extends OperateRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (): mixed => $this->payload());
    }
}
