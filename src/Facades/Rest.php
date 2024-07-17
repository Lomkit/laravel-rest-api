<?php

namespace Lomkit\Rest\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lomkit\Rest\Http\Routing\PendingResourceRegistration resource(string $name, $controller, array $options = [])
 * @method static \Lomkit\Rest\Rest                                     withDocumentationCallback(\Closure $documentationCallback)
 *
 * @see \Lomkit\Rest\Rest
 */
class Rest extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'lomkit-rest';
    }
}
