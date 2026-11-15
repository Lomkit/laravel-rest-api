<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use JsonSerializable;

final readonly class NotFiniteValue implements JsonSerializable
{
    public function __construct(
        private float $value,
    ) {}

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return ['ratio' => $this->value];
    }
}
