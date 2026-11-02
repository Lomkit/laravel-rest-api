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
    public function it_runs_no_key_query_for_another_validate_only_name(): void
    {
        $this->captureQueries();

        $response = $this->precognitiveMutation([['operation' => 'update', 'key' => 99, 'attributes' => ['name' => 'Seating']]], 'mutate.0.attributes');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
        $this->assertSame([], $this->statements);
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
