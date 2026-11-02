<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\MutationNode;

class PayloadReadingMutateRequest extends MutateRequest
{
    /** @var list<MutationNode>|null */
    public static ?array $read = null;

    #[\Override]
    protected function passedValidation(): void
    {
        parent::passedValidation();

        self::$read = $this->payload();
    }
}
