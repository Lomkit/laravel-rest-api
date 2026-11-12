<?php

namespace Lomkit\Rest\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lomkit\Rest\Http\Routing\PendingResourceRegistration resource(string $name, class-string<\Lomkit\Rest\Http\Controllers\Controller> $controller)
 * @method static \Lomkit\Rest\Search\Run\PendingSearch search(class-string<\Lomkit\Rest\Http\Resource> $resource)
 * @method static void forgetAuthorizationsFor(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void forgetAuthorizations()
 * @method static void forgetRoutes()
 *
 * @see \Lomkit\Rest\Rest
 */
class Rest extends Facade
{
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return \Lomkit\Rest\Rest::class;
    }
}
