<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class ScrambleTestCase extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        Scramble::$extensions = [];

        parent::setUp();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, RestServiceProvider::class];
    }

    /** @return array<string, mixed> */
    protected function operation(string $path, string $method): array
    {
        return app(Generator::class)()['paths'][$path][$method];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function body(array $operation): array
    {
        return $operation['requestBody']['content']['application/json']['schema'];
    }

    /** @return array<string, mixed> */
    protected function mutateEntry(string $path): array
    {
        return $this->body($this->operation($path.'/mutate', 'post'))['properties']['mutate']['items']['properties'];
    }
}
