<?php

namespace Lomkit\Rest;

use Lomkit\Rest\Contracts\Http\Routing\Registrar;
use Lomkit\Rest\Documentation\Schemas\OpenAPI;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Http\Routing\ResourceRegistrar;

class Rest implements Registrar
{
    protected \Closure $documentationCallback;

    /**
     * Route a resource to a controller.
     *
     * @param  string  $name
     * @param  class-string<Controller>  $controller
     * @param  array  $options
     * @return \Lomkit\Rest\Http\Routing\PendingResourceRegistration
     */
    public function resource(string $name, string $controller, array $options = [])
    {
        if (app()->bound(ResourceRegistrar::class)) {
            $registrar = app()->make(ResourceRegistrar::class);
        } else {
            $registrar = new ResourceRegistrar(app('router'));
        }

        return new PendingResourceRegistration(
            $registrar, $name, $controller, $options
        );
    }

    public function withDocumentationCallback(\Closure $documentationCallback): Rest
    {
        $this->documentationCallback = $documentationCallback;
        return $this;
    }

    public function applyDocumentationCallback(OpenAPI $openAPI): OpenAPI
    {
        if (!isset($this->documentationCallback)) {
            return $openAPI;
        }
        return call_user_func($this->documentationCallback, $openAPI);
    }
}