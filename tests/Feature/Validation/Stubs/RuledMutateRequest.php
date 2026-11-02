<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\MutateRequest;

class RuledMutateRequest extends MutateRequest
{
    /** @var array<string, list<string>> */
    public static array $before = [];

    /** @var array<string, list<string>> */
    public static array $added = [];

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return [...self::$before, ...parent::rules(), ...self::$added];
    }
}
