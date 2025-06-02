<?php
namespace Lomkit\Rest\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\SoftDeletedModelController;
class RoutesTest extends \Lomkit\Rest\Tests\TestCase
{
    public function test_registering_resource()
    {
        Route::group(
            ['as' => 'api.', 'prefix' => 'api'],
            function () {
                \Lomkit\Rest\Facades\Rest::resource('dummy-models', ModelController::class);
            }
        );

        $this->assertRouteRegistered('api.dummy-models.mutate', ['POST'], 'api/dummy-models/mutate', ModelController::class.'@mutate');
        $this->assertRouteRegistered('api.dummy-models.search', ['POST'], 'api/dummy-models/search', ModelController::class.'@search');
        $this->assertRouteRegistered('api.dummy-models.destroy', ['DELETE'], 'api/dummy-models', ModelController::class.'@destroy');

        $this->assertRouteNotRegistered('api.models.restore');
        $this->assertRouteNotRegistered('api.models.forceDelete');
    }

    public function test_registering_soft_deleted_resource()
    {
        Route::group(
            ['as' => 'api.', 'prefix' => 'api'],
            function () {
                \Lomkit\Rest\Facades\Rest::resource('dummy-soft-deleted-models', SoftDeletedModelController::class)->withSoftDeletes();
            }
        );

        $this->assertRouteRegistered('api.dummy-soft-deleted-models.restore', ['POST'], 'api/dummy-soft-deleted-models/restore', SoftDeletedModelController::class.'@restore');
        $this->assertRouteRegistered('api.dummy-soft-deleted-models.forceDelete', ['DELETE'], 'api/dummy-soft-deleted-models/force', SoftDeletedModelController::class.'@forceDelete');
    }

    /**
     * Asserts that a route with the given signature is registered.
     *
     * @param string $name
     * @param array  $methods
     * @param string $uri
     * @param string $controller
     */
    protected function assertRouteRegistered(string $name, array $methods, string $uri, string $controller)
    {
        $routes = Route::getRoutes();
        /**
         * @var \Illuminate\Routing\Route $route
         */
        $route = $routes->getByName($name);

        if (!$route) {
            $this->fail("Route \"$name\" with uri \"{$uri}\" does not exist.");
        }
        $this->assertSame($methods, $route->methods);
        $this->assertSame($uri, $route->uri);
        $this->assertSame($controller, $route->action['controller']);
    }

    /**
     * Assert that a route with the given name is not registered.
     *
     * @param string $name
     */
    protected function assertRouteNotRegistered(string $name)
    {
        $this->assertNull(Route::getRoutes()->getByName($name));
    }
}
