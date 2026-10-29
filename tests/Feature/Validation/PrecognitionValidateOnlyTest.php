<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.precognition.enabled', true)]
class PrecognitionValidateOnlyTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_validates_the_keys_nested_under_a_validate_only_name(): void
    {
        $response = $this->precognitiveSearch(['limit' => 7], 'search');

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.limit is invalid.',
            'errors' => ['search.limit' => ['The selected search.limit is invalid.']],
        ]);
    }

    #[Test]
    public function it_validates_the_nodes_nested_under_a_validate_only_name(): void
    {
        $response = $this->precognitiveSearch(['filters' => [['nested' => [['field' => 'stock', 'value' => 3]]]]], 'search');

        $response->assertUnprocessable()->assertExactJson([
            'message' => "The 'field' field is not valid.",
            'errors' => ['search.filters.0.nested.0.field' => ["The 'field' field is not valid."]],
        ]);
    }

    #[Test]
    public function it_accepts_valid_keys_nested_under_a_validate_only_name(): void
    {
        $response = $this->precognitiveSearch(['limit' => 10, 'filters' => [['field' => 'name', 'value' => 'Desk lamp']]], 'search');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
    }

    #[Test]
    public function it_skips_the_keys_outside_the_validate_only_names(): void
    {
        $response = $this->precognitiveSearch(['limit' => 7, 'filters' => [['field' => 'name', 'value' => 'Desk lamp']]], 'search.filters');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
    }

    #[Test]
    public function it_does_not_extend_a_validate_only_name_to_a_longer_sibling_key(): void
    {
        $response = $this->precognitiveSearch(['limit' => 7], 'search.lim');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
    }

    /** @param array<string, mixed> $search */
    private function precognitiveSearch(array $search, string $validateOnly): TestResponse
    {
        return $this->postJson('/api/products/search', ['search' => $search], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => $validateOnly,
        ]);
    }
}
