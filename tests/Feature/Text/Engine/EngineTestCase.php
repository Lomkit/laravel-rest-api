<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherLineResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Text\TextTarget;

abstract class EngineTestCase extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Ledger];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScoutServiceProvider::class, RestServiceProvider::class];
    }

    /** @return array<string, array{string}> */
    public static function drivers(): array
    {
        return [
            'the collection engine' => ['collection'],
            'the database engine' => ['database'],
        ];
    }

    /** @param array<string, mixed> $payload */
    protected function context(array $payload = []): RestContext
    {
        return new RestContext(RestRequest::create('/api/vouchers/search', 'POST', $payload), $this->app);
    }

    /** @param class-string<Resource> $class */
    protected function resource(string $class, ?RestContext $context = null): Resource
    {
        $context ??= $this->context();
        $resource = $context->resource($class);

        $context->authorizer()->plan([
            Check::onClass($resource, 'viewAny'),
            Check::onClass($context->resource(VoucherLineResource::class), 'viewAny'),
        ]);

        return $resource;
    }

    /** @param class-string<Resource> $class */
    protected function target(string $class): TextTarget
    {
        $resource = $this->resource($class);

        return TextTarget::of($resource, $resource->context()->request);
    }

    protected function useEngine(object $engine): void
    {
        $driver = 'engine-'.spl_object_id($engine);

        $this->app->make(EngineManager::class)->extend($driver, fn (): object => $engine);

        config(['scout.driver' => $driver]);
    }
}
