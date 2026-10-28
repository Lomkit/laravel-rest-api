<?php

namespace Lomkit\Rest;

use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Lomkit\Rest\Contracts\Http\Routing\Registrar;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Http\Routing\ResourceRegistrar;

class Rest implements Registrar
{
    /**
     * Route a resource to a controller.
     *
     * @param  class-string<Controller>  $controller
     * @return PendingResourceRegistration
     */
    public function resource(string $name, string $controller, array $options = [])
    {
        if (app()->bound(ResourceRegistrar::class)) {
            $registrar = app()->make(ResourceRegistrar::class);
        } else {
            $registrar = new ResourceRegistrar(app('router'));
        }

        $pendingResourceRegistration = (new PendingResourceRegistration(
            $registrar,
            $name,
            $controller,
            $options
        ))
            ->middleware(EnforceExpectsJson::class);

        return tap($pendingResourceRegistration, function ($pendingResourceRegistration) {
            if (config('rest.precognition.enabled', false)) {
                $pendingResourceRegistration
                    ->middleware(HandlePrecognitiveRequests::class);
            }
        });
    }
}
