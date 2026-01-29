<?php

namespace Lomkit\Rest;

use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Lomkit\Rest\Contracts\Http\Routing\Registrar;
use Lomkit\Rest\Documentation\Schemas\OpenAPI;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Http\Routing\ResourceRegistrar;

class Rest implements Registrar
{
    protected \Closure $documentationCallback;

    /**
     * Route a resource to a controller.
     *
     * @param string                   $name
     * @param class-string<Controller> $controller
     * @param array                    $options
     *
     * @return \Lomkit\Rest\Http\Routing\PendingResourceRegistration
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

    /**
     * Set the documentation callback for OpenAPI.
     *
     * @param \Closure $documentationCallback
     *
     * @return Rest
     */
    public function withDocumentationCallback(\Closure $documentationCallback): Rest
    {
        $this->documentationCallback = $documentationCallback;

        return $this;
    }

    /**
     * Apply the documentation callback to the OpenAPI instance.
     *
     * @param OpenAPI $openAPI
     *
     * @return OpenAPI
     */
    public function applyDocumentationCallback(OpenAPI $openAPI): OpenAPI
    {
        if (!isset($this->documentationCallback)) {
            return $openAPI;
        }

        return call_user_func($this->documentationCallback, $openAPI);
    }
}
