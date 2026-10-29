<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Validation\Validator;

final class ValidationHookCalls
{
    /** @var list<array{string, list<string>}> */
    private array $calls = [];

    public function record(string $hook, Validator $validator): void
    {
        $this->calls[] = [$hook, $validator->errors()->keys()];
    }

    /** @return list<array{string, list<string>}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
