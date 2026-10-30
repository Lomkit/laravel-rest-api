<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class ConfigurableInstruction extends Instruction
{
    /** @param list<Field> $fields */
    public function __construct(private readonly array $fields) {}

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return $this->fields;
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        $query->where('featured', $fields['featured']);
    }
}
