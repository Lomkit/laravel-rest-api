<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Closure;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ConfigurableInstructionPostResource extends PostResource
{
    #[\Override]
    public function instructions(): array
    {
        return [
            ...parent::instructions(),
            ConfigurableInstruction::make([
                Boolean::make('featured')->rules(static fn (string $attribute, mixed $value, Closure $fail): null => null),
            ]),
        ];
    }
}
