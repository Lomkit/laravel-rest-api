<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Authorization\Check;

final class UnplannedCheckException extends LogicException
{
    public static function for(Check $check): self
    {
        $resource = $check->resource::class;

        return new self("The [{$check->ability}] check on [{$resource}] was not planned before validation. Record it in the reach of the request.");
    }
}
