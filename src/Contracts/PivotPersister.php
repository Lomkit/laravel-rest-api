<?php

namespace Lomkit\Rest\Contracts;

use Lomkit\Rest\Mutations\PivotWrite;

interface PivotPersister
{
    public function attach(PivotWrite $write): void;

    public function detach(PivotWrite $write): void;

    public function updatePivot(PivotWrite $write): void;
}
