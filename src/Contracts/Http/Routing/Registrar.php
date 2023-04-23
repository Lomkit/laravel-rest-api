<?php

namespace Lomkit\Rest\Contracts\Http\Routing;

interface Registrar
{
    /**
     * Route a resource to a controller.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array  $options
     * @return Lomkit\Rest\Http\Routing\PendingResourceRegistration
     */
    public function resource($name, $controller, array $options = []);
}