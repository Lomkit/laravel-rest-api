<?php

namespace Lomkit\Rest\Tests\Concerns;

trait CompilesRoutes
{
    private function useCompiledRoutes(): void
    {
        $router = $this->app['router'];
        $routes = $router->getRoutes();
        $routes->refreshNameLookups();
        $routes->refreshActionLookups();

        foreach ($routes->getRoutes() as $route) {
            $route->prepareForSerialization();
        }

        $file = (string) tempnam(sys_get_temp_dir(), 'routes');

        try {
            file_put_contents($file, '<?php return '.var_export($routes->compile(), true).';');
            $router->setCompiledRoutes(require $file);
        } finally {
            unlink($file);
        }
    }
}
