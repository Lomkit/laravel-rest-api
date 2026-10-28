<?php

namespace Lomkit\Rest\Contracts\Http\Routing;

use Lomkit\Rest\Http\Controllers\Controller;

interface Registrar
{
    /**
     * Route a resource to a controller.
     *
     *
     * @return Lomkit\Rest\Http\Routing\PendingResourceRegistration
     */
    public function resource(string $name, string $controller, array $options = []);
}
