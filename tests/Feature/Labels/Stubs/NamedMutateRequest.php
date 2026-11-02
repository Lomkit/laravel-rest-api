<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Http\Requests\MutateRequest;

class NamedMutateRequest extends MutateRequest
{
    /** @var array<string, string> */
    public static array $names = [];

    /** @var array<string, string> */
    public static array $messages = [];

    /** @return array<string, string> */
    #[\Override]
    public function attributes(): array
    {
        return self::$names;
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return self::$messages;
    }
}
