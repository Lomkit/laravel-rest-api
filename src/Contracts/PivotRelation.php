<?php

namespace Lomkit\Rest\Contracts;

use Lomkit\Rest\Fields\FieldSet;

interface PivotRelation
{
    public function getPivotFields(): FieldSet;

    /** @return array<string, list<mixed>> */
    public function pivotRules(string $prefix): array;
}
