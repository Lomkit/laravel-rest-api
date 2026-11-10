<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class Utf8InputTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_refuses_an_attribute_that_is_not_valid_utf8_and_writes_nothing(): void
    {
        $lighting = Category::create(['name' => 'Lighting']);
        $lamp = Product::create(['category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 40]);

        $response = $this->send('/api/products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $lamp->id, 'attributes' => ['name' => 'Desk lamp XL']],
            ['operation' => 'create', 'attributes' => ['category_id' => $lighting->id, 'name' => "Floor lamp\xFF", 'price' => 90]],
        ]]);

        $this->assertExactErrors($response, ['mutate.1.attributes.name' => ['The mutate.1.attributes.name field must be valid UTF-8.']]);
        $this->assertSame([$lamp->id => 'Desk lamp'], Product::query()->pluck('name', 'id')->all());
    }

    #[Test]
    public function it_refuses_a_filter_value_that_is_not_valid_utf8(): void
    {
        $response = $this->send('/api/products/search', ['search' => ['filters' => [['field' => 'name', 'value' => "Desk\xFF"]]]]);

        $this->assertExactErrors($response, ['search.filters.0.value' => ['The search.filters.0.value field must be valid UTF-8.']]);
    }

    #[Test]
    public function it_refuses_a_key_that_is_not_valid_utf8_on_the_path_of_that_key(): void
    {
        $lighting = Category::create(['name' => 'Lighting']);

        $response = $this->send('/api/products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['category_id' => $lighting->id, "na\xFFme" => 'Floor lamp', 'price' => 90]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.na?me' => ['The mutate.0.attributes.na?me field must be valid UTF-8.']]);
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function it_refuses_a_query_string_value_that_is_not_valid_utf8(): void
    {
        $response = $this->send('/api/products/search?note=Q4%FF', []);

        $this->assertExactErrors($response, ['note' => ['The note field must be valid UTF-8.']]);
    }

    #[Test]
    public function it_answers_only_the_values_that_are_not_valid_utf8(): void
    {
        $response = $this->send('/api/products/search', ['search' => [
            'filters' => [['field' => 'name', 'operator' => 'near', 'value' => "Desk\xFF"], ['field' => 'unknown', 'value' => "Lamp\xFE"]],
        ]]);

        $this->assertExactErrors($response, [
            'search.filters.0.value' => ['The search.filters.0.value field must be valid UTF-8.'],
            'search.filters.1.value' => ['The search.filters.1.value field must be valid UTF-8.'],
        ]);
    }

    #[Test]
    public function it_answers_a_value_that_is_not_valid_utf8_in_french(): void
    {
        $this->app->setLocale('fr');

        $response = $this->send('/api/products/search', ['search' => ['filters' => [['field' => 'name', 'value' => "Desk\xFF"]]]]);

        $this->assertExactErrors($response, ['search.filters.0.value' => ['Le champ search.filters.0.value doit être en UTF-8 valide.']]);
    }

    /** @param array<string, mixed> $body */
    private function send(string $uri, array $body): TestResponse
    {
        return $this->post($uri, $body, ['Accept' => 'application/json']);
    }
}
