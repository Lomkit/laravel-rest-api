<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\ResolvedAuthorController;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\ResolvedAuthorResource;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\ResolvedPostController;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\TitledAuthorController;
use Lomkit\Rest\Tests\Feature\Serialization\SeedsPublishingRows;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class VirtualFieldTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ResolvedAuthorResource::$resolved = [];
        $this->seedPublishingRows();
        Route::prefix('api')->group(function (): void {
            Rest::resource('resolved-authors', ResolvedAuthorController::class)->register();
            Rest::resource('resolved-posts', ResolvedPostController::class)->register();
            Rest::resource('titled-authors', TitledAuthorController::class)->register();
        });
    }

    #[Test]
    public function it_returns_a_virtual_field_with_a_resolver_by_default(): void
    {
        $response = $this->postJson('/api/resolved-authors/search', ['search' => ['sorts' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null, 'display_name' => 'Ada Lovelace', 'preferences' => '{"theme":"dark"}'],
            ['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'settings' => null, 'display_name' => 'Grace Hopper', 'preferences' => '{"theme":"dark"}'],
        ]);
        $this->assertSame(['id', 'first_name', 'last_name', 'settings', 'display_name', 'preferences'], array_keys($response->json('data.0')));
        $this->assertSame([1, 2], ResolvedAuthorResource::$resolved);
    }

    #[Test]
    public function it_selects_a_virtual_field_with_a_resolver(): void
    {
        $response = $this->postJson('/api/resolved-authors/search', ['search' => [
            'selects' => [['field' => 'id'], ['field' => 'display_name']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'display_name' => 'Ada Lovelace'],
            ['id' => 2, 'display_name' => 'Grace Hopper'],
        ]);
    }

    #[Test]
    public function it_calls_the_resolver_of_returned_fields_only(): void
    {
        $response = $this->postJson('/api/resolved-authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk();
        $this->assertSame([], ResolvedAuthorResource::$resolved);
    }

    #[Test]
    public function it_calls_the_resolver_once_per_row_for_a_field_selected_twice(): void
    {
        $response = $this->postJson('/api/resolved-authors/search', ['search' => [
            'selects' => [['field' => 'display_name'], ['field' => 'display_name']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['display_name' => 'Ada Lovelace'],
            ['display_name' => 'Grace Hopper'],
        ]);
        $this->assertSame([1, 2], ResolvedAuthorResource::$resolved);
    }

    #[Test]
    public function it_returns_the_value_of_a_resolver_in_place_of_the_column_after_the_columns_and_the_aggregates(): void
    {
        $response = $this->postJson('/api/titled-authors/search', ['search' => [
            'aggregates' => [['relation' => 'posts', 'type' => 'count']],
            'sorts' => [['field' => 'last_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 2, 'last_name' => 'Hopper', 'posts_count' => 0, 'first_name' => 'Dr Grace'],
            ['id' => 1, 'last_name' => 'Lovelace', 'posts_count' => 2, 'first_name' => 'Dr Ada'],
        ]);
        $this->assertSame(['id', 'last_name', 'posts_count', 'first_name'], array_keys($response->json('data.0')));
    }

    #[Test]
    #[DataProvider('unselectable')]
    public function it_refuses_a_virtual_field_that_is_not_selectable(string $uri, string $field): void
    {
        $response = $this->postJson($uri, ['search' => ['selects' => [['field' => $field]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.selects.0.field' => ['The selected search.selects.0.field is invalid.'],
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function unselectable(): array
    {
        return [
            'a resolver on a field declared selectable(false)' => ['/api/resolved-authors/search', 'signature'],
            'a virtual field without a resolver' => ['/api/authors/search', 'initials'],
        ];
    }

    #[Test]
    public function it_returns_a_virtual_field_at_an_include_level(): void
    {
        $response = $this->postJson('/api/resolved-posts/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'author', 'selects' => [['field' => 'display_name']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'author' => ['display_name' => 'Ada Lovelace']],
            ['id' => 2, 'author' => ['display_name' => 'Ada Lovelace']],
            ['id' => 3, 'author' => null],
        ]);
        $this->assertSame([1, 1], ResolvedAuthorResource::$resolved);
    }

    #[Test]
    public function it_lists_a_virtual_field_with_a_resolver_as_selectable_in_the_details(): void
    {
        $response = $this->getJson('/api/resolved-authors');

        $response
            ->assertOk()
            ->assertJsonPath('data.fields.*.name', ['id', 'first_name', 'last_name', 'full_name', 'initials', 'api_token', 'settings', 'created_at', 'display_name', 'signature', 'preferences'])
            ->assertJsonPath('data.fields.*.select', [true, true, true, false, false, false, true, false, true, false, true]);
    }
}
