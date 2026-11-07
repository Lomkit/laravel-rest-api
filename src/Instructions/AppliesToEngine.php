<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Text\Engine\EngineQuery;

interface AppliesToEngine
{
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void;
}
