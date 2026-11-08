<?php

namespace Lomkit\Rest\Metrics;

interface GroupValues
{
    /** @return list<array{key: int|string|bool|null, value: int|float|null}> */
    public function groups(): array;

    /** @param list<int|string|bool|null> $kept */
    public function others(array $kept): int|float|null;
}
