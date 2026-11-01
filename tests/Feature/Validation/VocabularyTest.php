<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Validation\Search\SqlSearchGrammar;
use PHPUnit\Framework\Attributes\Test;

class VocabularyTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_builds_each_list_once_per_resource_mode_and_name(): void
    {
        $context = RestRequest::create('/', 'POST')
            ->setContainer($this->app)
            ->context();
        $vocabulary = $context->vocabulary();
        $products = $context->resource(ProductResource::class);
        $sql = $context->text($products)->grammar();
        $engine = (new EngineGrammar([], []))->searchGrammar(new TextCapabilities([], [TextMode::Words], true, false));
        $calls = 0;
        $names = function () use (&$calls): array {
            $calls++;

            return ['name', 'price'];
        };

        $sorts = $vocabulary->of($products, $sql, 'sorts', $names);

        $this->assertInstanceOf(SqlSearchGrammar::class, $sql);
        $this->assertSame($sorts, $vocabulary->of($products, $sql, 'sorts', $names));
        $this->assertSame($sorts, $vocabulary->of($products, $sql->nested(), 'sorts', $names));
        $this->assertSame(['name', 'price'], $sorts->values());
        $this->assertSame(1, $calls);

        $vocabulary->of($products, $engine, 'sorts', $names);
        $this->assertSame(2, $calls);

        $vocabulary->of($context->resource(CategoryResource::class), $sql, 'sorts', $names);
        $this->assertSame(3, $calls);

        $vocabulary->of($products, $sql, 'selects', $names);
        $this->assertSame(4, $calls);

        $this->assertSame($vocabulary, $context->vocabulary());
    }
}
