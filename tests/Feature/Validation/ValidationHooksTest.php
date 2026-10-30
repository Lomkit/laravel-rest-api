<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\HookedSearchRequest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\ValidationHookCalls;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ValidationHooksTest extends PackageTestCase
{
    private ValidationHookCalls $calls;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = $this->app->instance(ValidationHookCalls::class, new ValidationHookCalls);
        $this->app->bind(SearchRequest::class, HookedSearchRequest::class);
    }

    #[Test]
    public function it_runs_the_request_hooks_once_against_every_error(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => [
            'filters' => [
                ['field' => 'stock', 'value' => 3],
                ['nested' => [['field' => 'name', 'operator' => 'near', 'value' => 'Desk lamp']]],
            ],
            'includes' => [['relation' => 'category', 'filters' => [['field' => 'label', 'value' => 'Lighting']]]],
        ]]);

        $response->assertUnprocessable();
        $this->assertSame([
            ['withValidator', ['search.filters.0.field', 'search.filters.1.nested.0.operator', 'search.includes.0.filters.0.field']],
            ['after', ['search.filters.0.field', 'search.filters.1.nested.0.operator', 'search.includes.0.filters.0.field']],
        ], $this->calls->calls());
    }

    #[Test]
    public function it_runs_the_request_hooks_once_on_a_valid_search(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => [
            'filters' => [['nested' => [['field' => 'name', 'value' => 'Desk lamp']]]],
            'includes' => [['relation' => 'category']],
        ]]);

        $response->assertOk();
        $this->assertSame([['withValidator', []], ['after', []]], $this->calls->calls());
    }
}
