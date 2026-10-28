<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ScrambleDocumentTest extends PackageTestCase
{
    protected function setUp(): void
    {
        Scramble::$extensions = [];

        parent::setUp();
    }

    protected function fixtures(): array
    {
        return [new Catalog];
    }

    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, RestServiceProvider::class];
    }

    #[Test]
    public function it_documents_the_search_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products/search', 'post');

        $this->assertSame('Search Product', $operation['summary']);
        $this->assertSame(
            ['id', 'category_id', 'name', 'price'],
            $this->body($operation)['properties']['search']['properties']['filters']['items']['properties']['field']['enum'],
        );
    }

    #[Test]
    public function it_documents_the_mutate_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products/mutate', 'post');

        $this->assertSame('Mutate Product', $operation['summary']);
        $this->assertSame(
            ['create', 'update'],
            $this->body($operation)['properties']['mutate']['items']['properties']['operation']['enum'],
        );
    }

    #[Test]
    public function it_documents_the_destroy_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products', 'delete');

        $this->assertSame('Delete Product', $operation['summary']);
        $this->assertSame('integer', $this->body($operation)['properties']['resources']['items']['type']);
    }

    /** @return array<string, mixed> */
    private function operation(string $path, string $method): array
    {
        return app(Generator::class)()['paths'][$path][$method];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function body(array $operation): array
    {
        return $operation['requestBody']['content']['application/json']['schema'];
    }
}
