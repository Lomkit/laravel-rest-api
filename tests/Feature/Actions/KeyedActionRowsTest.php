<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\LoadedArtworkController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RecordArtworks;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artist;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artwork;
use Lomkit\Rest\Tests\Fixtures\Gallery\Collector;
use Lomkit\Rest\Tests\Fixtures\Gallery\Exhibition;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KeyedActionRowsTest extends PackageTestCase
{
    private const array MARK = ['name' => 'mark-loaded-artworks'];

    private const array BY_TITLE = [['field' => 'title']];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Gallery];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RecordArtworks::$runs = [];
        Rest::resource('loaded-artworks', LoadedArtworkController::class)->register();
        Artist::create(['id' => 1, 'name' => 'Ada']);
        Artist::create(['id' => 2, 'name' => 'Grace']);
        Collector::create(['id' => 1, 'name' => 'Alan']);
        Artwork::create(['id' => 1, 'artist_id' => 1, 'title' => 'Sea', 'medium' => 'oil', 'owner_type' => Collector::class, 'owner_id' => 1]);
        Artwork::create(['id' => 2, 'artist_id' => 2, 'title' => 'Dawn', 'medium' => 'ink']);
        Artwork::create(['id' => 3, 'artist_id' => 1, 'title' => 'Field', 'medium' => 'watercolor', 'owner_type' => Collector::class, 'owner_id' => 1]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<list<array{id: int, title: string, relations: array<string, mixed>}>>  $runs
     */
    #[Test]
    #[DataProvider('instructedSearches')]
    public function it_runs_on_rows_that_the_instruction_of_a_sorted_or_limited_search_decorated(array $search, array $runs): void
    {
        $response = $this->postJson('/loaded-artworks/actions/record-artworks', ['search' => $search]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame($runs, RecordArtworks::$runs);
    }

    /** @return array<string, array{array<string, mixed>, list<list<array{id: int, title: string, relations: array<string, mixed>}>>}> */
    public static function instructedSearches(): array
    {
        return [
            'a sort' => [
                ['instructions' => [self::MARK], 'sorts' => self::BY_TITLE],
                [[self::row(2, 'Dawn (loaded)'), self::row(3, 'Field (loaded)')], [self::row(1, 'Sea (loaded)')]],
            ],
            'a limit' => [
                ['instructions' => [self::MARK], 'limit' => 10],
                [[self::row(3, 'Field (loaded)'), self::row(2, 'Dawn (loaded)')], [self::row(1, 'Sea (loaded)')]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $include
     * @param  list<list<array{id: int, title: string, relations: array<string, mixed>}>>  $runs
     */
    #[Test]
    #[DataProvider('includes')]
    public function it_runs_on_rows_with_the_include_of_a_sorted_search_loaded(array $include, array $runs): void
    {
        $response = $this->postJson('/loaded-artworks/actions/record-artworks', ['search' => ['includes' => [$include], 'sorts' => self::BY_TITLE]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame($runs, RecordArtworks::$runs);
    }

    /** @return array<string, array{array<string, mixed>, list<list<array{id: int, title: string, relations: array<string, mixed>}>>}> */
    public static function includes(): array
    {
        return [
            'a morph to include' => [
                ['relation' => 'owner'],
                [[self::row(2, 'Dawn', ['owner' => null]), self::row(3, 'Field', ['owner' => 'Alan'])], [self::row(1, 'Sea', ['owner' => 'Alan'])]],
            ],
            'an aliased include' => [
                ['relation' => 'artist', 'alias' => 'painter'],
                [[self::row(2, 'Dawn', ['painter' => 'Grace']), self::row(3, 'Field', ['painter' => 'Ada'])], [self::row(1, 'Sea', ['painter' => 'Ada'])]],
            ],
        ];
    }

    #[Test]
    public function it_reads_the_keys_without_the_aggregates_of_the_rows(): void
    {
        Exhibition::create(['id' => 1, 'title' => 'Spring']);
        DB::table('artwork_exhibition')->insert([['artwork_id' => 1, 'exhibition_id' => 1], ['artwork_id' => 3, 'exhibition_id' => 1]]);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', $query->sql);
        });

        $response = $this->postJson('/loaded-artworks/actions/record-artworks', ['search' => [
            'aggregates' => [['relation' => 'exhibitions', 'type' => 'count', 'alias' => 'shows']],
            'sorts' => [['field' => 'shows', 'direction' => 'desc'], ...self::BY_TITLE],
        ]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertStringStartsWith('select artworks.id from artworks ', $queries[0]);
        $this->assertSame([[self::row(3, 'Field'), self::row(1, 'Sea')], [self::row(2, 'Dawn')]], RecordArtworks::$runs);
    }

    /**
     * @param  array<string, mixed>  $relations
     * @return array{id: int, title: string, relations: array<string, mixed>}
     */
    private static function row(int $id, string $title, array $relations = []): array
    {
        return ['id' => $id, 'title' => $title, 'relations' => $relations];
    }
}
