<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Database\Eloquent\MissingAttributeException;
use LogicException;

final class MissingColumnException extends LogicException
{
    /** @param class-string $resource */
    public static function readOn(string $resource, MissingAttributeException $previous): self
    {
        preg_match('/\[(.+?)\].*\[(.+)\]/', $previous->getMessage(), $names);
        $column = $names[1] ?? '?';
        $model = $names[2] ?? '?';

        return new self("The [{$resource}] search did not load the column [{$column}] that a policy or a field of [{$model}] reads. Add it to alwaysSelect() of the resource or to requires() of the field, or set rest.search.select_columns to false.", 0, $previous);
    }
}
