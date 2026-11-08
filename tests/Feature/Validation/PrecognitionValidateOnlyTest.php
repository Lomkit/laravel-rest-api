<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.precognition.enabled', true)]
class PrecognitionValidateOnlyTest extends PackageTestCase
{
    use CapturesQueries;

    #[\Override]
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
            'message' => 'The selected search.filters.0.nested.0.field is invalid.',
            'errors' => ['search.filters.0.nested.0.field' => ['The selected search.filters.0.nested.0.field is invalid.']],
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

    #[Test]
    public function it_reports_a_list_over_its_limit_on_the_list_key(): void
    {
        config(['rest.limits.sizes.filters' => 2]);
        $message = 'The search.filters field must not have more than 2 items.';

        $response = $this->precognitiveSearch(['filters' => $this->threeStockFilters()], 'search.filters');

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters' => [$message]]]);
    }

    #[Test]
    public function it_reports_a_list_over_its_limit_to_a_name_below_it(): void
    {
        config(['rest.limits.sizes.filters' => 2]);
        $message = 'The search.filters field must not have more than 2 items.';

        $response = $this->precognitiveSearch(['filters' => $this->threeStockFilters()], 'search.filters.0.field');

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters' => [$message]]]);
    }

    #[Test]
    public function it_reports_a_filter_that_is_not_an_object_to_a_name_under_it(): void
    {
        $response = $this->precognitiveSearch(['filters' => ['stock']], 'search.filters.0.field');

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.filters.0 field must be an array.',
            'errors' => ['search.filters.0' => ['The search.filters.0 field must be an array.']],
        ]);
    }

    #[Test]
    public function it_reports_the_field_of_a_filter_to_its_value(): void
    {
        $response = $this->precognitiveSearch(['filters' => [['field' => 'stock', 'value' => 3]]], 'search.filters.0.value');

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.filters.0.field is invalid.',
            'errors' => ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']],
        ]);
    }

    #[Test]
    public function it_reports_an_unknown_include_relation_to_a_name_under_it(): void
    {
        $message = 'The search.includes.0.relation field is not a valid or allowed relation for this resource.';

        $response = $this->precognitiveSearch(['includes' => [['relation' => 'supplier', 'filters' => [['field' => 'name', 'value' => 'Acme']]]]], 'search.includes.0.filters.0.field');

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.includes.0.relation' => [$message]]]);
    }

    #[Test]
    public function it_reports_an_unknown_instruction_to_a_name_under_it(): void
    {
        $response = $this->precognitiveSearch(['instructions' => [['name' => 'priced-under', 'fields' => [['name' => 'price', 'value' => 'cheap']]]]], 'search.instructions.0.fields.price');

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.instructions.0.name is invalid.',
            'errors' => ['search.instructions.0.name' => ['The selected search.instructions.0.name is invalid.']],
        ]);
    }

    #[Test]
    public function it_leaves_out_the_errors_beside_the_ancestors_of_a_name(): void
    {
        $response = $this->precognitiveSearch([
            'limit' => 7,
            'filters' => [['field' => 'price', 'value' => 'cheap'], ['field' => 'stock', 'value' => 3]],
        ], 'search.filters.0.value');

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.filters.0.value field must be an integer.',
            'errors' => ['search.filters.0.value' => ['The search.filters.0.value field must be an integer.']],
        ]);
    }

    #[Test]
    public function it_checks_no_key_when_the_keys_are_not_validated(): void
    {
        $this->captureQueries();

        $response = $this->deleteJson('/api/products', ['resources' => [999], 'search' => []], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'search',
        ]);

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
        $this->assertSame([], $this->readsOf('products'));
    }

    /** @return list<array<string, mixed>> */
    private function threeStockFilters(): array
    {
        return array_fill(0, 3, ['field' => 'stock', 'value' => 3]);
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
