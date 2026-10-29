<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class InstructionValidationTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidFields')]
    public function it_reports_the_field_errors_under_their_keys(string $instruction, array $fields, array $errors): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => ['instructions' => [['name' => $instruction, 'fields' => $fields]]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, array<string, list<string>>}> */
    public static function invalidFields(): array
    {
        return [
            'a value breaking the field type' => ['highlight-posts', [['name' => 'featured', 'value' => 'yes']], [
                'search.instructions.0.fields.featured' => ['The featured field must be true or false.'],
            ]],
            'an explicit null on a field that is not nullable' => ['highlight-posts', [['name' => 'featured', 'value' => null]], [
                'search.instructions.0.fields.featured' => ['The featured field must be true or false.'],
            ]],
            'an unknown field name' => ['highlight-posts', [['name' => 'pinned', 'value' => true]], [
                'search.instructions.0.fields.0.name' => ['The selected search.instructions.0.fields.0.name is invalid.'],
            ]],
            'a field name that is not a string' => ['highlight-posts', [['name' => ['featured'], 'value' => true]], [
                'search.instructions.0.fields.0.name' => [
                    'The search.instructions.0.fields.0.name field must be a string.',
                    'The selected search.instructions.0.fields.0.name is invalid.',
                ],
            ]],
            'an entry without a name' => ['highlight-posts', [['value' => true]], [
                'search.instructions.0.fields.0.name' => ['The search.instructions.0.fields.0.name field is required.'],
            ]],
            'a missing required field' => ['in-status', [], [
                'search.instructions.0.fields.status' => ['The status field is required.'],
            ]],
            'a required field sent without a value' => ['in-status', [['name' => 'status']], [
                'search.instructions.0.fields.status' => ['The status field is required.'],
            ]],
            'a required_if condition met by another field' => ['in-status', [['name' => 'status', 'value' => 'published']], [
                'search.instructions.0.fields.since' => ['The since field is required when status is published.'],
            ]],
            'an unknown enum case' => ['in-status', [['name' => 'status', 'value' => 'archived']], [
                'search.instructions.0.fields.status' => ['The selected status is invalid.'],
            ]],
        ];
    }

    #[Test]
    public function it_rejects_an_instruction_the_resource_does_not_declare(): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => ['instructions' => [['name' => 'archive-posts']]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.instructions.0.name is invalid.',
            'errors' => [
                'search.instructions.0.name' => ['The selected search.instructions.0.name is invalid.'],
            ],
        ]);
    }

    #[Test]
    public function it_reports_a_field_error_with_its_message(): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'min_views', 'value' => 'many']]]]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The min views field must be an integer.',
            'errors' => [
                'search.instructions.0.fields.min_views' => ['The min views field must be an integer.'],
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidScoutFields')]
    public function it_validates_the_fields_of_an_instruction_declared_only_for_scout(array $fields, array $errors): void
    {
        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => ['value' => 'lamp'],
                'instructions' => [['name' => 'priced-between', 'fields' => $fields]],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function invalidScoutFields(): array
    {
        return [
            'a missing required field' => [[['name' => 'min', 'value' => 10]], [
                'search.instructions.0.fields.max' => ['The max field is required.'],
            ]],
            'a value breaking the field type' => [[['name' => 'max', 'value' => 'cheap']], [
                'search.instructions.0.fields.max' => ['The max field must be an integer.'],
            ]],
            'an unknown field name' => [[['name' => 'max', 'value' => 39], ['name' => 'currency', 'value' => 'EUR']], [
                'search.instructions.0.fields.1.name' => ['The selected search.instructions.0.fields.1.name is invalid.'],
            ]],
        ];
    }

    #[Test]
    public function it_rejects_a_scout_instruction_outside_scout_mode(): void
    {
        $response = $this->postJson('/api/products/search', [
            'search' => ['instructions' => [['name' => 'priced-between', 'fields' => [['name' => 'max', 'value' => 39]]]]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.instructions.0.name is invalid.',
            'errors' => [
                'search.instructions.0.name' => ['The selected search.instructions.0.name is invalid.'],
            ],
        ]);
    }
}
