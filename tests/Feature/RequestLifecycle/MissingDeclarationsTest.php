<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\UndeclaredModelController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\UndeclaredModelResource;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\UndeclaredResourceController;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MissingDeclarationsTest extends PackageTestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function handWrittenRoutes(): iterable
    {
        yield 'details' => ['GET', 'api/drafts', 'details'];
        yield 'search' => ['POST', 'api/drafts/search', 'search'];
        yield 'mutate' => ['POST', 'api/drafts/mutate', 'mutate'];
        yield 'operate' => ['POST', 'api/drafts/actions/publish', 'operate'];
        yield 'destroy' => ['DELETE', 'api/drafts', 'destroy'];
        yield 'restore' => ['POST', 'api/drafts/restore', 'restore'];
        yield 'force destroy' => ['DELETE', 'api/drafts/force', 'forceDestroy'];
    }

    /** @return iterable<string, array{string, class-string<RestRequest>}> */
    public static function endpointRequests(): iterable
    {
        yield 'details' => ['details', DetailsRequest::class];
        yield 'search' => ['search', SearchRequest::class];
        yield 'mutate' => ['mutate', MutateRequest::class];
        yield 'operate' => ['operate', OperateRequest::class];
        yield 'destroy' => ['destroy', DestroyRequest::class];
        yield 'restore' => ['restore', RestoreRequest::class];
        yield 'force destroy' => ['forceDestroy', ForceDestroyRequest::class];
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function modelEndpoints(): iterable
    {
        yield 'details' => ['GET', '/papers', []];
        yield 'search' => ['POST', '/papers/search', []];
        yield 'mutate' => ['POST', '/papers/mutate', ['mutate' => [['operation' => 'create']]]];
        yield 'destroy' => ['DELETE', '/papers', ['resources' => [1]]];
        yield 'restore' => ['POST', '/papers/restore', ['resources' => [1]]];
        yield 'force destroy' => ['DELETE', '/papers/force', ['resources' => [1]]];
    }

    #[Test]
    public function it_names_the_controller_without_a_resource_when_its_routes_register(): void
    {
        $before = $this->app['router']->getRoutes()->count();

        try {
            Rest::resource('drafts', UndeclaredResourceController::class);
            $this->fail('The registration was not rejected.');
        } catch (InvalidControllerException $exception) {
            $this->assertSame($this->missingResourceMessage(), $exception->getMessage());
        }

        $this->assertSame($before, $this->app['router']->getRoutes()->count());
    }

    #[Test]
    #[DataProvider('handWrittenRoutes')]
    public function it_names_the_controller_without_a_resource_when_a_hand_written_route_reaches_it(string $method, string $uri, string $endpoint): void
    {
        Route::match([$method], $uri, [UndeclaredResourceController::class, $endpoint]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidControllerException($this->missingResourceMessage()));

        $this->json($method, '/'.$uri);
    }

    /** @param class-string<RestRequest> $request */
    #[Test]
    #[DataProvider('endpointRequests')]
    public function it_names_the_controller_without_a_resource_when_its_endpoint_runs(string $endpoint, string $request): void
    {
        $this->expectExceptionObject(new InvalidControllerException($this->missingResourceMessage()));

        (new UndeclaredResourceController)->{$endpoint}($request::create('/api/drafts')->setContainer($this->app));
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('modelEndpoints')]
    public function it_names_the_resource_without_a_model_when_an_endpoint_reaches_it(string $method, string $uri, array $body): void
    {
        Rest::resource('papers', UndeclaredModelController::class)->withSoftDeletes()->register();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidResourceException(
            'The resource ['.UndeclaredModelResource::class.'] does not declare its model. Add public static string $model = YourModel::class; to it.',
        ));

        $this->json($method, $uri, $body);
    }

    private function missingResourceMessage(): string
    {
        return 'The controller ['.UndeclaredResourceController::class.'] does not declare its resource. Add public static string $resource = YourResource::class; to it.';
    }
}
