<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\ScrambleServiceProvider;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class ScrambleTestCase extends PackageTestCase
{
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

    /** @return array<string, mixed> */
    protected function detailsSchema(string $path): array
    {
        $document = app(Generator::class)();
        $reference = $document['paths'][$path]['get']['responses'][200]['content']['application/json']['schema']['$ref'];

        return $document['components']['schemas'][substr($reference, strlen('#/components/schemas/'))];
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
