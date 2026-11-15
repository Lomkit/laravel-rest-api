<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use JsonSerializable;

final readonly class MetaDto implements JsonSerializable
{
    public function __construct(
        private mixed $data,
    ) {}

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return ['data' => $this->data];
    }
}
