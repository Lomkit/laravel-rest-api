<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artist;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artwork;
use Lomkit\Rest\Tests\Fixtures\Gallery\Collector;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToIncludeTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Gallery];
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
    }

    #[Test]
    public function it_writes_the_type_first_in_each_target(): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [
            ['@type' => Book::class, 'id' => 1, 'title' => 'Dune'],
            ['@type' => Book::class, 'id' => 2, 'title' => 'Emma'],
        ]);
        $this->assertSame(['@type', 'id', 'title'], array_keys($response->json('data.0.labelable')));
    }

    #[Test]
    public function it_renders_a_morph_to_include_after_the_includes_loaded_with_the_rows(): void
    {
        Artist::create(['id' => 1, 'name' => 'Ada']);
        Collector::create(['id' => 1, 'name' => 'Grace']);
        Artwork::create(['id' => 1, 'artist_id' => 1, 'title' => 'Sea', 'medium' => 'oil', 'owner_type' => Collector::class, 'owner_id' => 1]);

        $response = $this->postJson('/api/artworks/search', ['search' => ['selects' => [['field' => 'id']], 'includes' => [['relation' => 'owner'], ['relation' => 'artist']]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'artist' => ['id' => 1, 'name' => 'Ada'], 'owner' => ['@type' => Collector::class, 'id' => 1, 'name' => 'Grace']]]);
        $this->assertSame(['id', 'artist', 'owner'], array_keys($response->json('data.0')));
    }

    #[Test]
    public function it_names_the_type_after_the_config(): void
    {
        config(['rest.morph.type_key' => 'kind']);

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.0.labelable', ['kind' => Book::class, 'id' => 1, 'title' => 'Dune']);
    }

    #[Test]
    public function it_filters_and_aggregates_a_target_like_any_include(): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => [
            'includes' => [[
                'relation' => 'labelable',
                'filters' => [['field' => 'title', 'value' => 'Dune']],
                'aggregates' => [['relation' => 'labels', 'type' => 'count']],
            ]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [['@type' => Book::class, 'id' => 1, 'title' => 'Dune', 'labels_count' => 1], null]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function nestedIncludes(): array
    {
        return [
            'an include of the target' => [['relation' => 'labelable', 'includes' => [['relation' => 'loan']]]],
            'a dotted include' => [['relation' => 'labelable.loan']],
        ];
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('nestedIncludes')]
    public function it_loads_the_includes_of_a_target(array $include): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [$include], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [
            ['@type' => Book::class, 'id' => 1, 'title' => 'Dune', 'loan' => ['id' => 1, 'borrower' => 'Ada', 'book_id' => 1]],
            ['@type' => Book::class, 'id' => 2, 'title' => 'Emma', 'loan' => ['id' => 2, 'borrower' => 'Bob', 'book_id' => 2]],
        ]);
        $this->assertCount(4, DB::getQueryLog());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unorderedIncludes(): array
    {
        return [
            'the default order' => [['relation' => 'labelable']],
        ];
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('unorderedIncludes')]
    public function it_loads_the_targets_without_an_order_or_a_limit(array $include): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [$include], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable.id', [1, 2]);
        $query = DB::getQueryLog()[2]['query'];
        $this->assertStringContainsString('books', $query);
        $this->assertStringNotContainsString('order by', $query);
        $this->assertStringNotContainsString('limit', $query);
    }

    #[Test]
    public function it_refuses_the_types_of_a_morph_to_include(): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable', 'types' => []]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.includes.0.types' => ['The search.includes.0.types field is prohibited.']]);
    }

    #[Test]
    public function it_checks_the_target_before_any_query(): void
    {
        Gate::policy(Book::class, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable']]]]);

        $response->assertForbidden()->assertJsonPath('message', 'This list is closed.');
        $this->assertSame([], DB::getQueryLog());
    }
}
