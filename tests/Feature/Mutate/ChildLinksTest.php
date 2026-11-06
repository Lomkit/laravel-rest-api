<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ChildLinksTest extends PackageTestCase
{
    use AssertsExactErrors;
    use SeedsLibraryRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Author::class, OpenPublishingPolicy::class);
        $this->seedLibrary();
        Author::query()->insert([
            ['id' => 101, 'first_name' => 'Ada', 'last_name' => 'Byron', 'api_token' => 'token-1'],
            ['id' => 102, 'first_name' => 'Mary', 'last_name' => 'Shelley', 'api_token' => 'token-2'],
        ]);
        Post::query()->insert([
            ['id' => 101, 'author_id' => 101, 'title' => 'Notes', 'status' => 'draft', 'published_on' => '2026-01-01'],
            ['id' => 102, 'author_id' => 102, 'title' => 'Frankenstein', 'status' => 'draft', 'published_on' => '2026-01-01'],
        ]);
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('foreignDetaches')]
    public function it_refuses_a_detach_of_a_row_that_is_not_a_child_of_the_parent(string $uri, array $entry, string $key): void
    {
        $rows = [self::library(), self::posts()];

        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame($rows, [self::library(), self::posts()]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function foreignDetaches(): array
    {
        return [
            'has one' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.loan.key'],
            'has one of many' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['latestLoan' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.latestLoan.key'],
            'morph one' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.label.key'],
            'morph one of many' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['latestLabel' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.latestLabel.key'],
            'morph many, a row of another type' => ['/api/books/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'detach', 'key' => [101, 104]]]]], 'mutate.0.relations.labels.0.key'],
            'has many' => ['/api/authors/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['posts' => [['operation' => 'detach', 'key' => [102]]]]], 'mutate.0.relations.posts.0.key'],
            'has one, under a created parent' => ['/api/books/mutate', ['operation' => 'create', 'attributes' => ['title' => 'Persuasion'], 'relations' => ['loan' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.loan.key'],
            'has many, under a created parent' => ['/api/authors/mutate', ['operation' => 'create', 'attributes' => ['first_name' => 'Jane', 'last_name' => 'Austen', 'api_token' => 'token-3'], 'relations' => ['posts' => [['operation' => 'detach', 'key' => [101]]]]], 'mutate.0.relations.posts.0.key'],
        ];
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('foreignUpdates')]
    public function it_refuses_an_update_of_a_row_of_another_parent(string $uri, array $entry, string $key): void
    {
        $rows = [self::library(), self::posts()];

        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame($rows, [self::library(), self::posts()]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function foreignUpdates(): array
    {
        return [
            'has one' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']]]], 'mutate.0.relations.loan.key'],
            'has one of many' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['latestLoan' => ['operation' => 'update', 'key' => 103, 'attributes' => ['borrower' => 'Alan Turing']]]], 'mutate.0.relations.latestLoan.key'],
            'morph one' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'update', 'key' => 101, 'attributes' => ['text' => 'old']]]], 'mutate.0.relations.label.key'],
            'morph one of many' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['latestLabel' => ['operation' => 'update', 'key' => 103, 'attributes' => ['text' => 'old']]]], 'mutate.0.relations.latestLabel.key'],
            'morph many' => ['/api/books/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'update', 'key' => 102, 'attributes' => ['text' => 'old']]]]], 'mutate.0.relations.labels.0.key'],
            'has many' => ['/api/authors/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['posts' => [['operation' => 'update', 'key' => 102, 'attributes' => ['views' => 7]]]]], 'mutate.0.relations.posts.0.key'],
            'morph many, under a created parent' => ['/api/books/mutate', ['operation' => 'create', 'attributes' => ['title' => 'Persuasion'], 'relations' => ['labels' => [['operation' => 'update', 'key' => 101, 'attributes' => ['text' => 'old']]]]], 'mutate.0.relations.labels.0.key'],
            'has many, under a created parent' => ['/api/authors/mutate', ['operation' => 'create', 'attributes' => ['first_name' => 'Jane', 'last_name' => 'Austen', 'api_token' => 'token-3'], 'relations' => ['posts' => [['operation' => 'update', 'key' => 101, 'attributes' => ['views' => 7]]]]], 'mutate.0.relations.posts.0.key'],
        ];
    }

    #[Test]
    public function it_updates_and_detaches_the_children_of_the_parent(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => [
                'loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']],
                'label' => ['operation' => 'detach', 'key' => 101],
            ]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['latestLoan' => ['operation' => 'detach', 'key' => 102]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['Ada Lovelace > Dune', 'Alan > -', 'Grace > -', 'Hidden > Ulysses'], self::library()['loans']);
        $this->assertSame(['classic > - -', 'loose > - -', 'modern > Book Emma', 'shelved > Shelf Classics'], self::library()['labels']);
    }

    #[Test]
    public function it_clears_the_type_of_a_detached_morph_many_row(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'detach', 'key' => [101]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['classic > - -', 'loose > - -', 'modern > Book Emma', 'shelved > Shelf Classics'], self::library()['labels']);
    }

    #[Test]
    public function it_moves_a_row_of_another_parent_with_attach(): void
    {
        $response = $this->postJson('/api/authors/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['posts' => [['operation' => 'attach', 'key' => [102]], ['operation' => 'update', 'key' => 102, 'attributes' => ['views' => 7]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['Frankenstein > 101 7', 'Notes > 101 0'], self::posts());
    }

    #[Test]
    public function it_checks_each_entry_after_the_entries_before_it(): void
    {
        $response = $this->postJson('/api/authors/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['posts' => [['operation' => 'attach', 'key' => [101]]]]],
            ['operation' => 'update', 'key' => 101, 'relations' => ['posts' => [['operation' => 'detach', 'key' => [101]]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.1.relations.posts.0.key' => ['The selected mutate.1.relations.posts.0.key is invalid.']]);
        $this->assertSame(['Frankenstein > 102 0', 'Notes > 101 0'], self::posts());
    }

    /** @return list<string> */
    private static function posts(): array
    {
        return Post::query()
            ->orderBy('title')
            ->get()
            ->map(fn (Post $post): string => $post->getAttribute('title').' > '.($post->getAttribute('author_id') ?? '-').' '.$post->getAttribute('views'))
            ->all();
    }
}
