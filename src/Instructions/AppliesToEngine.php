<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Text\Engine\EngineQuery;

interface AppliesToEngine
{
    /** @param array<string, mixed> $fields */
    public function handleEngine(array $fields, EngineQuery $query): void;
}
