<?php

namespace Lomkit\Rest\Authorization;

interface DecisionStore
{
    /**
     * @param  array<string, Check>  $checks
     * @return array<string, Decision>
     */
    public function many(array $checks): array;

    /**
     * @param  array<string, Check>  $checks
     * @param  array<string, Decision>  $decisions
     */
    public function putMany(array $checks, array $decisions): void;
}
