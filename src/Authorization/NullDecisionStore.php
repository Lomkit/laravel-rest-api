<?php

namespace Lomkit\Rest\Authorization;

final readonly class NullDecisionStore implements DecisionStore
{
    #[\Override]
    public function many(array $checks): array
    {
        return [];
    }

    #[\Override]
    public function putMany(array $checks, array $decisions): void {}
}
