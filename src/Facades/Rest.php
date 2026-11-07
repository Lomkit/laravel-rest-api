<?php

namespace Lomkit\Rest\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lomkit\Rest\Http\Routing\PendingResourceRegistration resource(string $name, class-string<\Lomkit\Rest\Http\Controllers\Controller> $controller)
 * @method static void forgetAuthorizationsFor(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void forgetAuthorizations()
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
