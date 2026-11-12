<?php

namespace Lomkit\Rest\Mutations;

final readonly class DeletedRow
{
    public function __construct(
        public string $path,
        public string $identity,
    ) {}
}
