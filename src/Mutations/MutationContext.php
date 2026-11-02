<?php

namespace Lomkit\Rest\Mutations;

use Lomkit\Rest\Fields\MutationOperation;

final readonly class MutationContext
{
    public function __construct(
        public MutationOperation $operation,
        public int|string|null $key,
        public int $depth,
    ) {}
}
