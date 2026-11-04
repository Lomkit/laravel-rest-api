<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MorphToAliasTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Book::create(['id' => 1, 'title' => 'Dune']);
        Book::create(['id' => 2, 'title' => 'Emma']);
        Loan::create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Loan::create(['id' => 2, 'book_id' => 2, 'borrower' => 'Bob']);
        Label::create(['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);
        Label::create(['id' => 2, 'text' => 'novel', 'labelable_type' => Book::class, 'labelable_id' => 2]);
        Label::create(['id' => 5, 'text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]);
        Label::create(['id' => 6, 'text' => 'stray', 'labelable_type' => null, 'labelable_id' => 1]);
    }

    #[Test]
    public function it_loads_an_aliased_morph_to_like_the_relation_itself(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => [
            'includes' => [['relation' => 'labelable'], ['relation' => 'labelable', 'alias' => 'target']],
            'sorts' => [['field' => 'id']],
        ]]);

        $books = [['@type' => Book::class, 'id' => 1, 'title' => 'Dune'], ['@type' => Book::class, 'id' => 2, 'title' => 'Emma'], null, null];
        $response->assertOk()
            ->assertJsonPath('data.*.target', $books)
            ->assertJsonPath('data.*.labelable', $books);
        $this->assertCount(3, DB::getQueryLog());
    }

    #[Test]
    public function it_loads_the_includes_of_an_aliased_morph_to(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => [
            'includes' => [['relation' => 'labelable', 'alias' => 'target', 'includes' => [['relation' => 'loan']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.target', [
            ['@type' => Book::class, 'id' => 1, 'title' => 'Dune', 'loan' => ['id' => 1, 'borrower' => 'Ada', 'book_id' => 1]],
            ['@type' => Book::class, 'id' => 2, 'title' => 'Emma', 'loan' => ['id' => 2, 'borrower' => 'Bob', 'book_id' => 2]],
            null,
            null,
        ]);
        $this->assertCount(3, DB::getQueryLog());
    }

    #[Test]
    public function it_loads_an_aliased_morph_to_under_an_include(): void
    {
        $response = $this->postJson('/api/books/search', ['search' => [
            'includes' => [['relation' => 'labels', 'includes' => [['relation' => 'labelable', 'alias' => 'target']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.0.labels.0.target', ['@type' => Book::class, 'id' => 1, 'title' => 'Dune'])
            ->assertJsonPath('data.1.labels.0.target', ['@type' => Book::class, 'id' => 2, 'title' => 'Emma']);
    }

    #[Test]
    public function it_loads_an_aliased_include_of_an_aliased_morph_to(): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => [
            'includes' => [['relation' => 'labelable', 'alias' => 'target', 'includes' => [['relation' => 'loan', 'alias' => 'lent']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.target.lent.borrower', ['Ada', 'Bob', null, null]);
    }

    #[Test]
    public function it_counts_a_relation_of_an_aliased_morph_to(): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => [
            'includes' => [['relation' => 'labelable', 'alias' => 'target', 'aggregates' => [['relation' => 'labels', 'type' => 'count']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.target.labels_count', [1, 1, null, null]);
    }
}
