<?php

namespace Lomkit\Rest\Authorization;

final readonly class NullDecisionStore implements DecisionStore
{
    public function many(array $checks): array
    {
        return [];
    }

    public function putMany(array $checks, array $decisions): void {}
}
