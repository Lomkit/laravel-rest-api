<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

final readonly class ControllerResources
{
    /**
     * @param  list<string>  $controllers
     * @return list<class-string<Resource>>
     */
    public static function of(array $controllers): array
    {
        $resources = [];

        foreach ($controllers as $controller) {
            if (! is_subclass_of($controller, Controller::class)) {
                continue;
            }

            try {
                $resource = $controller::resourceClass();
            } catch (InvalidControllerException) {
                continue;
            }

            if (! in_array($resource, $resources, true)) {
                $resources[] = $resource;
            }
        }

        return $resources;
    }
}
