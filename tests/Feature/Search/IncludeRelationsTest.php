<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IncludeRelationsTest extends PackageTestCase
{
    use AssertsExactErrors;
    use SeedsNewsroomRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Newsroom, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedNewsroomRows();
        DB::table('books')->insert([['id' => 1, 'title' => 'Dune'], ['id' => 2, 'title' => 'Emma'], ['id' => 3, 'title' => 'Walden']]);
        DB::table('loans')->insert([['id' => 1, 'book_id' => 1, 'borrower' => 'Ada'], ['id' => 2, 'book_id' => 2, 'borrower' => 'Grace'], ['id' => 3, 'book_id' => null, 'borrower' => 'Alan']]);
        DB::table('labels')->insert([['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1], ['id' => 2, 'text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]]);
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('includes')]
    public function it_renders_each_kind_of_include_for_every_parent(string $uri, array $include, mixed $expected): void
    {
        $response = $this->postJson($uri, ['search' => ['includes' => [$include]]]);

        $response->assertOk()->assertJsonPath('data', $expected);
    }

    /** @return array<string, array{string, array<string, mixed>, mixed}> */
    public static function includes(): array
    {
        return [
            'a has one' => ['/api/books/search', ['relation' => 'loan'], self::books('loan')],
            'a has one of many' => ['/api/books/search', ['relation' => 'latestLoan'], self::books('latest_loan')],
            'a has one through' => ['/news-desks/search', ['relation' => 'leadSummary'], [['id' => 3, 'name' => 'Arts', 'lead_summary' => null], ['id' => 2, 'name' => 'Sport', 'lead_summary' => ['id' => 2, 'body' => 'Big']], ['id' => 1, 'name' => 'City', 'lead_summary' => ['id' => 1, 'body' => 'Mild']]]],
            'an aliased belongs to' => ['/api/loans/search', ['relation' => 'book', 'alias' => 'borrowed'], [['id' => 3, 'borrower' => 'Alan', 'book_id' => null, 'borrowed' => null], ['id' => 2, 'borrower' => 'Grace', 'book_id' => 2, 'borrowed' => ['id' => 2, 'title' => 'Emma']], ['id' => 1, 'borrower' => 'Ada', 'book_id' => 1, 'borrowed' => ['id' => 1, 'title' => 'Dune']]]],
            'an alias written in camel case' => ['/api/books/search', ['relation' => 'loan', 'alias' => 'currentLoan'], self::books('currentLoan')],
            'an aliased morph to' => ['/api/labels/search', ['relation' => 'labelable', 'alias' => 'target'], [['id' => 2, 'text' => 'loose', 'labelable_id' => null, 'labelable_type' => null, 'target' => null], ['id' => 1, 'text' => 'classic', 'labelable_id' => 1, 'labelable_type' => Book::class, 'target' => ['@type' => Book::class, 'id' => 1, 'title' => 'Dune']]]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unknownRelations')]
    public function it_refuses_an_include_of_a_relation_the_resource_does_not_declare(array $search, string $key): void
    {
        $response = $this->postJson('/api/loans/search', ['search' => $search]);

        $this->assertExactErrors($response, [$key => ["The {$key} field is not a valid or allowed relation for this resource."]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unknownRelations(): array
    {
        return [
            'the second segment of a dotted include' => [['includes' => [['relation' => 'book.authors']]], 'search.includes.0.relation'],
            'a nested include' => [['includes' => [['relation' => 'book', 'includes' => [['relation' => 'authors']]]]], 'search.includes.0.includes.0.relation'],
        ];
    }

    #[Test]
    public function it_narrows_only_the_last_level_of_a_three_level_include(): void
    {
        DB::table('authors')->insert([['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1']]);
        DB::table('posts')->insert([['id' => 1, 'author_id' => 1, 'title' => 'Notes', 'status' => 'published', 'views' => 3, 'published_on' => '2026-01-01']]);
        DB::table('tags')->insert([['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics']]);
        DB::table('author_tag')->insert([['author_id' => 1, 'tag_id' => 1]]);

        $response = $this->postJson('/api/authors/search', ['search' => ['includes' => [['relation' => 'posts.author.tags', 'selects' => [['field' => 'id']]]]]]);

        $response->assertOk()
            ->assertJsonPath('data.0.posts.0.author', ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null, 'tags' => [['id' => 1, 'pivot' => []]]])
            ->assertJsonPath('data.0.posts.0.title', 'Notes')
            ->assertJsonPath('data.0.posts.0.views', 3);
    }

    /** @return list<array<string, mixed>> */
    private static function books(string $key): array
    {
        return [
            ['id' => 3, 'title' => 'Walden', $key => null],
            ['id' => 2, 'title' => 'Emma', $key => ['id' => 2, 'borrower' => 'Grace', 'book_id' => 2]],
            ['id' => 1, 'title' => 'Dune', $key => ['id' => 1, 'borrower' => 'Ada', 'book_id' => 1]],
        ];
    }
}
