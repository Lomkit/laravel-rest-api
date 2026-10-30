<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MutatePayloadValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidMutations')]
    public function it_rejects_a_mutation_whose_keyed_objects_are_not_arrays(array $mutation, array $errors): void
    {
        $response = $this->postJson('/api/products/mutate', ['mutate' => [$mutation]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(0, Product::query()->count());
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidMutations(): array
    {
        return [
            'attributes given as a string' => [['operation' => 'create', 'attributes' => 'Desk lamp'], [
                'mutate.0.attributes' => ['The mutate.0.attributes field must be an array.'],
            ]],
            'relations given as a string' => [['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39], 'relations' => 'category'], [
                'mutate.0.relations' => ['The mutate.0.relations field must be an array.'],
            ]],
            'an undeclared attribute' => [['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'stock' => 3]], [
                'mutate.0.attributes' => ['The stock key is not valid for the mutate.0.attributes field.'],
            ]],
        ];
    }
}
