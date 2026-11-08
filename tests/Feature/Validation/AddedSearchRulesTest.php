<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\RuledFirstSearchRequest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\RuledSearchRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AddedSearchRulesTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(SearchRequest::class, RuledSearchRequest::class);
    }

    #[Test]
    public function it_adds_an_application_rule_to_the_package_rules_of_a_search_key(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['limit' => 5]]);

        $this->assertExactErrors($response, ['search.limit' => ['The selected search.limit is invalid.']]);
    }

    #[Test]
    public function it_applies_an_application_rule_to_a_search_key_that_the_package_does_not_read(): void
    {
        $refused = $this->postJson('/api/products/search', ['search' => ['flag' => 'maybe']]);
        $accepted = $this->postJson('/api/products/search', ['search' => ['flag' => true]]);

        $this->assertExactErrors($refused, ['search.flag' => ['The search.flag field must be true or false.']]);
        $accepted->assertOk()->assertJsonPath('data', []);
    }

    #[Test]
    #[DataProvider('prices')]
    public function it_keeps_the_package_rules_of_a_sent_input_that_an_application_rule_marks_sometimes(mixed $price, string $message): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['instructions' => [
            ['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => $price]]],
        ]]]);

        $this->assertExactErrors($response, ['search.instructions.0.fields.price' => [$message]]);
    }

    /** @return array<string, array{mixed, string}> */
    public static function prices(): array
    {
        return [
            'a package rule' => ['x', 'The price field must be an integer.'],
            'the application rule' => [600, 'The price field must not be greater than 500.'],
        ];
    }

    #[Test]
    public function it_expands_an_application_wildcard_rule_written_before_the_parent_rules(): void
    {
        $this->app->bind(SearchRequest::class, RuledFirstSearchRequest::class);

        $response = $this->postJson('/api/products/search', ['search' => ['instructions' => [
            ['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => 600]]],
        ]]]);

        $this->assertExactErrors($response, ['search.instructions.0.fields.price' => ['The price field must not be greater than 500.']]);
    }

    #[Test]
    public function it_applies_an_application_rule_to_a_search_key_that_is_absent(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['sorts' => [['field' => 'name']]]]);

        $this->assertExactErrors($response, ['search.sorts.0.direction' => ['The search.sorts.0.direction field is required.']]);
    }
}
