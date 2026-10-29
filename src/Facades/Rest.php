<?php

namespace Lomkit\Rest\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lomkit\Rest\Http\Routing\PendingResourceRegistration resource(string $name, class-string<\Lomkit\Rest\Http\Controllers\Controller> $controller)
 *
 * @see \Lomkit\Rest\Rest
 */
class Rest extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Lomkit\Rest\Rest::class;
    }
}
