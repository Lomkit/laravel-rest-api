<?php

namespace Lomkit\Rest\Facades;

use Illuminate\Support\Facades\Facade;

class Rest extends Facade
{
    /**
     * @method static \Lomkit\Rest\Http\Routing\PendingResourceRegistration resource(string $name, $controller, array $options = [])
     *
     * @see \Lomkit\Rest\Rest
     */
    protected static function getFacadeAccessor()
    {
        return 'lomkit-rest';
    }
}