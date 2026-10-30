<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ScopeValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_requires_a_position_whose_field_has_no_default(): void
    {
        $response = $this->searchPosts([['name' => 'publishedSince', 'parameters' => []]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters.0 field is required.',
            'errors' => [
                'search.scopes.0.parameters.0' => ['The search.scopes.0.parameters.0 field is required.'],
            ],
        ]);
    }

    #[Test]
    public function it_requires_the_positions_when_the_parameters_are_omitted(): void
    {
        $response = $this->searchPosts([['name' => 'publishedSince']]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters.0 field is required.',
            'errors' => [
                'search.scopes.0.parameters.0' => ['The search.scopes.0.parameters.0 field is required.'],
            ],
        ]);
    }

    #[Test]
    public function it_rejects_more_parameters_than_declared_fields(): void
    {
        $response = $this->searchPosts([['name' => 'popular', 'parameters' => [10, 20]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters field must not have more than 1 items.',
            'errors' => [
                'search.scopes.0.parameters' => ['The search.scopes.0.parameters field must not have more than 1 items.'],
            ],
        ]);
    }

    #[Test]
    #[DataProvider('parametersBreakingTheFieldRules')]
    public function it_rejects_a_parameter_that_breaks_the_field_rules(string $scope, mixed $parameter, string $message): void
    {
        $response = $this->searchPosts([['name' => $scope, 'parameters' => [$parameter]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => $message,
            'errors' => ['search.scopes.0.parameters.0' => [$message]],
        ]);
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function parametersBreakingTheFieldRules(): iterable
    {
        yield 'text for an integer' => ['popular', 'many', 'The search.scopes.0.parameters.0 field must be an integer.'];
        yield 'null for an integer with a default' => ['popular', null, 'The search.scopes.0.parameters.0 field must have a value.'];
        yield 'text for a date' => ['publishedSince', 'someday', 'The search.scopes.0.parameters.0 field must be a valid date.'];
        yield 'null for a date' => ['publishedSince', null, 'The search.scopes.0.parameters.0 field is required.'];
        yield 'a value outside an int-backed enum' => ['reaching', 4, 'The selected search.scopes.0.parameters.0 is invalid.'];
        yield 'false for an int-backed enum without a zero case' => ['reaching', false, 'The selected search.scopes.0.parameters.0 is invalid.'];
    }

    #[Test]
    public function it_requires_a_nullable_position_without_default_to_be_present(): void
    {
        $response = $this->searchPosts([['name' => 'viewedBetween', 'parameters' => [5]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters.1 field must be present.',
            'errors' => [
                'search.scopes.0.parameters.1' => ['The search.scopes.0.parameters.1 field must be present.'],
            ],
        ]);
    }

    #[Test]
    public function it_checks_a_position_against_the_rules_declared_on_its_field(): void
    {
        $response = $this->searchPosts([['name' => 'viewedBetween', 'parameters' => [0, null]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters.0 field must be at least 1.',
            'errors' => [
                'search.scopes.0.parameters.0' => ['The search.scopes.0.parameters.0 field must be at least 1.'],
            ],
        ]);
    }

    #[Test]
    public function it_rejects_named_parameters(): void
    {
        $response = $this->searchPosts([['name' => 'publishedSince', 'parameters' => ['since' => '2026-01-01']]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters field must be a list. (and 1 more error)',
            'errors' => [
                'search.scopes.0.parameters' => ['The search.scopes.0.parameters field must be a list.'],
                'search.scopes.0.parameters.0' => ['The search.scopes.0.parameters.0 field is required.'],
            ],
        ]);
    }

    #[Test]
    public function it_rejects_a_scope_the_resource_does_not_declare(): void
    {
        $response = $this->searchPosts([['name' => 'archived', 'parameters' => [1]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.scopes.0.name is invalid.',
            'errors' => [
                'search.scopes.0.name' => ['The selected search.scopes.0.name is invalid.'],
            ],
        ]);
    }

    /** @param list<array<string, mixed>> $scopes */
    private function searchPosts(array $scopes): TestResponse
    {
        return $this->postJson('/api/posts/search', ['search' => ['scopes' => $scopes]]);
    }
}
