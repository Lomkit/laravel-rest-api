<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Search\SqlSearchGrammar;
use Lomkit\Rest\Validation\ValidationContext;
use PHPUnit\Framework\Attributes\Test;

class ValidationContextTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_starts_from_the_root_resource_and_the_request_limits(): void
    {
        $rules = new ParsedRules;
        $rest = RestRequest::create('/', 'POST')
            ->setContainer($this->app)
            ->context();
        $context = ValidationContext::root($rest->resource(ProductResource::class), new NodeBudget, $rules);

        $this->assertInstanceOf(ProductResource::class, $context->resource);
        $this->assertInstanceOf(SqlSearchGrammar::class, $context->grammar);
        $this->assertSame($rest->limits(), $context->limits);
        $this->assertSame($rules, $context->rules);
        $this->assertSame($rest->vocabulary(), $context->vocabulary);
        $this->assertSame([0, 0], [$context->filterDepth, $context->includeDepth]);
    }

    #[Test]
    public function it_changes_one_level_at_a_time(): void
    {
        $context = $this->root();
        $categories = $context->resource->context()->resource(CategoryResource::class);

        $deeper = $context->deeper()->deeper();
        $other = $deeper->for($categories);
        $included = $deeper->included(3);

        $this->assertSame([2, 0], [$deeper->filterDepth, $deeper->includeDepth]);
        $this->assertSame([$categories, $deeper->grammar, 2], [$other->resource, $other->grammar, $other->filterDepth]);
        $this->assertSame([$deeper->grammar, 2, 3], [$included->grammar, $included->filterDepth, $included->includeDepth]);
        $this->assertSame([$context->rules, $context->reader, $context->vocabulary], [$included->rules, $included->reader, $included->vocabulary]);
    }

    #[Test]
    public function it_starts_the_search_of_a_relation_at_the_first_filter_level_in_the_nested_grammar(): void
    {
        $context = $this->root();
        $categories = $context->resource->context()->resource(CategoryResource::class);
        $engine = (new EngineGrammar([], []))->searchGrammar(new TextCapabilities([], [TextMode::Words], true, false));

        $related = (new ValidationContext($context->resource, $engine, $context->limits, $context->rules, $context->reader, $context->vocabulary))
            ->included(1)
            ->deeper()
            ->related($categories);

        $this->assertSame($categories, $related->resource);
        $this->assertInstanceOf(SqlSearchGrammar::class, $related->grammar);
        $this->assertSame(TextBackend::Query, $related->grammar->backend());
        $this->assertSame([0, 1], [$related->filterDepth, $related->includeDepth]);
    }

    private function root(): ValidationContext
    {
        $rest = RestRequest::create('/', 'POST')
            ->setContainer($this->app)
            ->context();

        return ValidationContext::root($rest->resource(ProductResource::class), new NodeBudget, new ParsedRules);
    }
}
