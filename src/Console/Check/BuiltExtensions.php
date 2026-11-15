<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Http\RestContext;
use Throwable;

final readonly class BuiltExtensions
{
    public static function in(RestContext $context): bool
    {
        try {
            $context->extensions()->all();
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
