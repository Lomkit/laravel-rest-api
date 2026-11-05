<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\ThemedAuthorController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\TrackedAuthor;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\TrackedAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RenderedColumnsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('tracked-authors', TrackedAuthorController::class)->register();
        Rest::resource('themed-authors', ThemedAuthorController::class)->register();
        Author::query()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada', 'settings' => ['theme' => 'dark']]);
        Author::query()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-grace']);
        TrackedAuthor::$read = [];
    }

    #[Test]
    public function it_converts_only_the_columns_that_a_row_renders(): void
    {
        $response = $this->postJson('/tracked-authors/search', ['search' => ['selects' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data', [['first_name' => 'Grace'], ['first_name' => 'Ada']]);
        $this->assertSame([], TrackedAuthor::$read);
    }

    #[Test]
    public function it_returns_an_appended_value_through_a_field_that_resolves_it(): void
    {
        $response = $this->postJson('/tracked-authors/search', ['search' => ['selects' => [['field' => 'theme']]]]);

        $response->assertOk()->assertJsonPath('data', [['theme' => 'light'], ['theme' => 'dark']]);
        $this->assertSame(['theme', 'theme'], TrackedAuthor::$read);
    }

    #[Test]
    public function it_does_not_return_an_appended_value_through_a_field_that_is_not_virtual(): void
    {
        config(['rest.search.select_columns' => false]);

        $response = $this->postJson('/themed-authors/search', ['search' => ['selects' => [['field' => 'first_name'], ['field' => 'theme']]]]);

        $response->assertOk()->assertJsonPath('data', [['first_name' => 'Grace'], ['first_name' => 'Ada']]);
        $this->assertSame([], TrackedAuthor::$read);
    }
}
