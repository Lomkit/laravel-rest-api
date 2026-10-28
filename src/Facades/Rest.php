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
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'lomkit-rest';
    }
}
