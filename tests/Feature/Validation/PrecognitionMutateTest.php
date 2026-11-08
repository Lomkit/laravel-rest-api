<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.precognition.enabled', true)]
class PrecognitionMutateTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
    }

    #[Test]
    public function it_validates_the_key_of_an_entry_by_its_name(): void
    {
        $this->captureQueries();

        $response = $this->precognitiveMutation([['operation' => 'update', 'key' => 99, 'attributes' => ['name' => 5]]], 'mutate.0.key');

        $this->assertExactErrors($response, ['mutate.0.key' => ['The selected mutate.0.key is invalid.']]);
    }

    #[Test]
    public function it_validates_the_key_of_an_entry_for_a_name_under_it(): void
    {
        $this->captureQueries();

        $response = $this->precognitiveMutation([['operation' => 'update', 'key' => 99, 'attributes' => ['name' => 'Seating']]], 'mutate.0.attributes.name');

        $this->assertExactErrors($response, ['mutate.0.key' => ['The selected mutate.0.key is invalid.']]);
        $this->assertCount(1, $this->statements);
        $this->assertCount(1, $this->readsOf('categories'));
    }

    #[Test]
    public function it_checks_the_key_once_for_a_name_under_its_entry(): void
    {
        $this->captureQueries();

        $response = $this->precognitiveMutation([['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Seating']]], 'mutate.0.attributes.name');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
        $this->assertCount(1, $this->statements);
        $this->assertCount(1, $this->readsOf('categories'));
    }

    #[Test]
    public function it_runs_no_key_query_for_a_name_under_another_entry(): void
    {
        $this->captureQueries();

        $response = $this->precognitiveMutation([
            ['operation' => 'update', 'key' => 99, 'attributes' => ['name' => 'Seating']],
            ['operation' => 'create', 'attributes' => ['name' => 'Outdoor']],
        ], 'mutate.1.attributes');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
        $this->assertSame([], $this->statements);
    }

    #[Test]
    public function it_reports_an_invalid_operation_to_a_name_under_its_entry(): void
    {
        $response = $this->precognitiveMutation([['operation' => 'upsert', 'attributes' => ['name' => 5]]], 'mutate.0.attributes.name');

        $this->assertExactErrors($response, ['mutate.0.operation' => ['The selected mutate.0.operation is invalid.']]);
    }

    #[Test]
    public function it_reports_an_entry_that_is_not_an_object_to_a_name_under_it(): void
    {
        $response = $this->precognitiveMutation(['Seating'], 'mutate.0.attributes.name');

        $this->assertExactErrors($response, ['mutate.0' => ['The mutate.0 field must be an array.']]);
    }

    #[Test]
    public function it_reports_an_undeclared_relation_to_a_name_under_it(): void
    {
        $response = $this->precognitiveMutation([[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['suppliers' => [['operation' => 'create', 'attributes' => ['name' => 5]]]],
        ]], 'mutate.0.relations.suppliers.0.attributes.name');

        $this->assertExactErrors($response, ['mutate.0.relations' => ['The suppliers key is not valid for the mutate.0.relations field.']]);
    }

    #[Test]
    public function it_reports_the_operation_of_each_entry_that_a_wildcard_name_reaches(): void
    {
        $response = $this->precognitiveMutation([
            ['operation' => 'upsert', 'attributes' => ['name' => 'Seating']],
            ['operation' => 'create', 'attributes' => ['name' => 5]],
        ], 'mutate.*.attributes.name');

        $this->assertExactErrors($response, [
            'mutate.0.operation' => ['The selected mutate.0.operation is invalid.'],
            'mutate.1.attributes.name' => ['The mutate.1.attributes.name field must be a string.'],
        ]);
    }

    #[Test]
    public function it_leaves_out_the_errors_of_the_other_entries_and_keys(): void
    {
        $response = $this->precognitiveMutation([
            ['operation' => 'create', 'attributes' => ['name' => 5], 'without_detaching' => 'yes'],
            ['operation' => 'upsert', 'attributes' => ['name' => 'Seating']],
        ], 'mutate.0.attributes.name');

        $this->assertExactErrors($response, ['mutate.0.attributes.name' => ['The mutate.0.attributes.name field must be a string.']]);
    }

    /** @param list<array<string, mixed>> $mutate */
    private function precognitiveMutation(array $mutate, string $validateOnly): TestResponse
    {
        return $this->postJson('/api/categories/mutate', ['mutate' => $mutate], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => $validateOnly,
        ]);
    }
}
