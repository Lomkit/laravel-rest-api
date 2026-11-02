<?php

namespace Lomkit\Rest\Tests\Feature\Labels;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledAuthorController;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchLabelsTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const array LABELLED = ['name' => 'labelled-highlight-posts', 'fields' => [['name' => 'min_views', 'value' => 'many']]];

    private const array UNLABELLED = ['name' => 'highlight-posts', 'fields' => [['name' => 'min_views', 'value' => 'many']]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('labelled-posts', LabelledPostController::class)->register();
        Rest::resource('labelled-authors', LabelledAuthorController::class)->register();
    }

    #[Test]
    public function it_names_an_instruction_field_by_its_label(): void
    {
        $response = $this->postJson('/labelled-posts/search', ['search' => ['instructions' => [self::LABELLED]]]);

        $this->assertExactErrors($response, ['search.instructions.0.fields.min_views' => ['The minimum views field must be an integer.']]);
    }

    /**
     * @param  list<array<string, mixed>>  $instructions
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('meetingFields')]
    public function it_names_each_instruction_field_by_its_own_declaration(array $instructions, array $errors): void
    {
        $response = $this->postJson('/labelled-posts/search', ['search' => ['instructions' => $instructions]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function meetingFields(): array
    {
        return [
            'the unlabelled field first' => [[self::UNLABELLED, self::LABELLED], [
                'search.instructions.0.fields.min_views' => ['The min views field must be an integer.'],
                'search.instructions.1.fields.min_views' => ['The minimum views field must be an integer.'],
            ]],
            'the labelled field first' => [[self::LABELLED, self::UNLABELLED], [
                'search.instructions.0.fields.min_views' => ['The minimum views field must be an integer.'],
                'search.instructions.1.fields.min_views' => ['The min views field must be an integer.'],
            ]],
        ];
    }

    #[Test]
    public function it_names_a_scope_parameter_by_its_label(): void
    {
        $response = $this->postJson('/labelled-posts/search', ['search' => ['scopes' => [['name' => 'publishedSince', 'parameters' => ['soon']]]]]);

        $this->assertExactErrors($response, ['search.scopes.0.parameters.0' => ['The start date field must be a valid date.']]);
    }

    #[Test]
    public function it_keeps_the_path_of_an_unlabelled_parameter_at_the_same_position(): void
    {
        $response = $this->postJson('/labelled-posts/search', ['search' => ['scopes' => [
            ['name' => 'popular', 'parameters' => ['many']],
            ['name' => 'publishedSince', 'parameters' => ['soon']],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.scopes.0.parameters.0' => ['The search.scopes.0.parameters.0 field must be an integer.'],
            'search.scopes.1.parameters.0' => ['The start date field must be a valid date.'],
        ]);
    }

    #[Test]
    public function it_names_the_fields_of_an_include_by_their_labels(): void
    {
        $response = $this->postJson('/labelled-authors/search', ['search' => ['includes' => [[
            'relation' => 'posts',
            'instructions' => [self::LABELLED],
            'scopes' => [['name' => 'publishedSince', 'parameters' => ['soon']]],
        ]]]]);

        $this->assertExactErrors($response, [
            'search.includes.0.scopes.0.parameters.0' => ['The start date field must be a valid date.'],
            'search.includes.0.instructions.0.fields.min_views' => ['The minimum views field must be an integer.'],
        ]);
    }

    #[Test]
    public function it_names_an_instruction_field_in_the_search_of_an_action(): void
    {
        $response = $this->postJson('/labelled-posts/actions/publish-posts', ['search' => ['instructions' => [self::LABELLED]]]);

        $this->assertExactErrors($response, ['search.instructions.0.fields.min_views' => ['The minimum views field must be an integer.']]);
    }
}
