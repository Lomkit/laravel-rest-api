<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\TagLabelledAuthorController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class IncludeKeyTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('tag-labelled-authors', TagLabelledAuthorController::class)->register();
        Author::create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada', 'settings' => null]);
    }

    #[Test]
    public function it_asks_for_an_alias_when_an_include_is_named_like_a_field(): void
    {
        $response = $this->postJson('/tag-labelled-authors/search', ['search' => ['includes' => [['relation' => 'tags']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.0.alias' => ['The search.includes.0.alias field is required, because the key tags is already used.'],
        ]);
    }

    #[Test]
    public function it_returns_the_field_next_to_an_aliased_include(): void
    {
        $response = $this->postJson('/tag-labelled-authors/search', ['search' => ['selects' => [['field' => 'tags']], 'includes' => [['relation' => 'tags', 'alias' => 'topics']]]]);

        $response->assertOk()->assertJsonPath('data', [['tags' => 'none', 'topics' => []]]);
    }

    #[Test]
    public function it_asks_for_an_alias_when_an_include_is_named_like_a_reserved_key(): void
    {
        config(['rest.morph.type_key' => 'loan']);
        Book::create(['id' => 1, 'title' => 'Dune']);
        Label::create(['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable', 'types' => [Book::class => ['includes' => [['relation' => 'loan']]]]]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.0.types.'.Book::class.'.includes.0.alias' => ['The search.includes.0.types.'.Book::class.'.includes.0.alias field is required, because the key loan is already used.'],
        ]);
    }
}
