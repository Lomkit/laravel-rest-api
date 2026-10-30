<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Http\Requests\RestRequest;

class ConfigurableAction extends Action
{
    /** @param list<Field> $fields */
    public function __construct(private readonly array $fields) {}

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return $this->fields;
    }
}
