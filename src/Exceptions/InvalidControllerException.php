<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Http\Controllers\Controller;

final class InvalidControllerException extends LogicException
{
    public static function missingResource(string $controller): self
    {
        return new self("The controller [{$controller}] does not declare its resource. Add public static string \$resource = YourResource::class; to it.");
    }

    public static function missingRoute(string $request): self
    {
        return new self("The request [{$request}] is not on a route to a controller that extends [".Controller::class.'].');
    }
}
