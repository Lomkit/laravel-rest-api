<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteControllerTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_reads_the_resource_of_a_route_whose_controller_is_not_built(): void
    {
        $request = DestroyRequest::create('/api/products', 'DELETE')->setContainer($this->app);
        $route = $this->app['router']->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        $rules = $request->rules();

        $this->assertNull($route->controller);
        $this->assertEquals(['distinct', 'integer', Rule::exists('products', 'id')], $rules['resources.*']);
    }

    #[Test]
    public function it_names_the_request_resolved_on_a_route_without_a_package_controller(): void
    {
        Route::delete('api/drafts', fn (DestroyRequest $request): array => []);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidControllerException(
            'The request ['.DestroyRequest::class.'] is not on a route to a controller that extends [Lomkit\Rest\Http\Controllers\Controller].',
        ));

        $this->deleteJson('/api/drafts', ['resources' => [1]]);
    }
}
