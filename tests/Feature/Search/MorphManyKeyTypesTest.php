<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Festival;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Note;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Show;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Stage;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MorphManyKeyTypesTest extends PackageTestCase
{
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Festival];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Show::create(['id' => 1, 'title' => 'Opening', 'featured' => true]);
        Show::create(['id' => 2, 'title' => 'Closing', 'featured' => false]);
        Show::create(['id' => 3, 'title' => 'Matinee', 'featured' => false]);
        Stage::create(['code' => 'main', 'name' => 'Main']);
        Stage::create(['code' => 'side', 'name' => 'Side']);
        Note::create(['id' => 1, 'body' => 'Sound check', 'notable_type' => Show::class, 'notable_id' => '1']);
        Note::create(['id' => 2, 'body' => 'Late start', 'notable_type' => Show::class, 'notable_id' => '2']);
        Note::create(['id' => 3, 'body' => 'Encore', 'notable_type' => Show::class, 'notable_id' => '1']);
        Note::create(['id' => 4, 'body' => 'Wet floor', 'notable_type' => Stage::class, 'notable_id' => 'main']);
        Tag::create(['id' => 1, 'name' => 'Loud']);
        Tag::create(['id' => 2, 'name' => 'Quiet']);
        DB::table('taggables')->insert([
            ['tag_id' => 1, 'taggable_type' => Show::class, 'taggable_id' => '1'],
            ['tag_id' => 2, 'taggable_type' => Show::class, 'taggable_id' => '2'],
            ['tag_id' => 1, 'taggable_type' => Stage::class, 'taggable_id' => 'main'],
        ]);
    }

    #[Test]
    public function it_filters_on_a_morph_many_relation(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['filters' => [['relation' => 'notes', 'nested' => [['field' => 'body', 'value' => 'Encore']]]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
        $this->assertSame([[
            "select shows.id, shows.title, shows.featured from shows where (exists (select * from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ? and (notes.body = ?))) order by shows.id desc limit 50 offset 0",
            [Show::class, 'Encore'],
        ]], $this->readsOf('shows'));
    }

    #[Test]
    public function it_filters_on_rows_without_a_morph_many_row(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['filters' => [['relation' => 'notes', 'exists' => false]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3]);
        $this->assertSame([[
            "select shows.id, shows.title, shows.featured from shows where (not exists (select * from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ?)) order by shows.id desc limit 50 offset 0",
            [Show::class],
        ]], $this->readsOf('shows'));
    }

    #[Test]
    public function it_filters_on_a_field_of_a_morph_many_relation(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['filters' => [['field' => 'notes.body', 'value' => 'Late start']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [2]);
        $this->assertSame([[
            "select shows.id, shows.title, shows.featured from shows where (exists (select * from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ? and notes.body = ?)) order by shows.id desc limit 50 offset 0",
            [Show::class, 'Late start'],
        ]], $this->readsOf('shows'));
    }

    #[Test]
    public function it_counts_and_sorts_on_a_morph_many_relation(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => [
            'aggregates' => [['relation' => 'notes', 'type' => 'count'], ['relation' => 'notes', 'type' => 'exists']],
            'sorts' => [['field' => 'notes_count', 'direction' => 'desc'], ['field' => 'id']],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [1, 2, 3])
            ->assertJsonPath('data.*.notes_count', [2, 1, 0])
            ->assertJsonPath('data.*.notes_exists', [true, true, false]);
        $this->assertSame([[
            "select shows.id, shows.title, shows.featured, (select count(*) from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ?) as @aggregate_0, exists(select * from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ?) as @aggregate_1 from shows order by (select count(*) from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ?) desc, shows.id asc limit 50 offset 0",
            [Show::class, Show::class, Show::class],
        ]], $this->readsOf('shows'));
    }

    #[Test]
    public function it_sorts_on_a_morph_one_relation(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['filters' => [['field' => 'id', 'operator' => 'in', 'value' => [1, 2]]], 'sorts' => [['field' => 'note.body'], ['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [2, 1]);
        $this->assertSame([[
            "select shows.id, shows.title, shows.featured from shows where (shows.id in (?, ?)) order by (select max(notes.body) from notes where shows.id{$text} = notes.notable_id and notes.notable_type = ?) asc, shows.id asc limit 50 offset 0",
            [1, 2, Show::class],
        ]], $this->readsOf('shows'));
    }

    #[Test]
    public function it_includes_a_morph_many_relation(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['includes' => [['relation' => 'notes']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 2, 1]);
        $this->assertSame([[], [2], [3, 1]], self::nested($response->json('data'), 'notes', 'id'));
        $this->assertSame([self::eager(
            'select * from (select notes.id, notes.body, notes.notable_id, notes.notable_type, row_number() over (partition by notes.notable_id order by notes.id desc) as laravel_row from notes where notes.notable_id in (%s) and notes.notable_type = ?) as laravel_table where laravel_row <= 11 order by laravel_row',
            [1, 2, 3],
            [Show::class],
        )], $this->readsOf('notes'));
    }

    #[Test]
    public function it_includes_a_morph_many_relation_under_an_alias(): void
    {
        $response = $this->postJson('/api/shows/search', ['search' => ['includes' => [['relation' => 'notes', 'alias' => 'remarks', 'filters' => [['field' => 'body', 'value' => 'Encore']]]]]]);

        $response->assertOk();
        $this->assertSame([[], [], [3]], self::nested($response->json('data'), 'remarks', 'id'));
    }

    #[Test]
    public function it_filters_on_a_morph_to_many_relation(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['filters' => [['relation' => 'tags', 'nested' => [['field' => 'name', 'value' => 'Quiet']]]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [2]);
        $this->assertSame([[
            "select shows.id, shows.title, shows.featured from shows where (exists (select * from tags inner join taggables on tags.id = taggables.tag_id where shows.id{$text} = taggables.taggable_id and taggables.taggable_type = ? and (tags.name = ?))) order by shows.id desc limit 50 offset 0",
            [Show::class, 'Quiet'],
        ]], $this->readsOf('shows'));
    }

    #[Test]
    public function it_counts_a_morph_to_many_relation(): void
    {
        $response = $this->postJson('/api/shows/search', ['search' => ['aggregates' => [['relation' => 'tags', 'type' => 'count']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.tags_count', [1, 1, 0]);
    }

    #[Test]
    public function it_includes_a_morph_to_many_relation(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/shows/search', ['search' => ['includes' => [['relation' => 'tags']]]]);

        $response->assertOk();
        $this->assertSame([[], [2], [1]], self::nested($response->json('data'), 'tags', 'id'));
        $this->assertSame([self::eager(
            'select * from (select tags.id, tags.name, taggables.taggable_id as pivot_taggable_id, taggables.tag_id as pivot_tag_id, taggables.taggable_type as pivot_taggable_type, row_number() over (partition by taggables.taggable_id order by tags.id desc) as laravel_row from tags inner join taggables on tags.id = taggables.tag_id where taggables.taggable_id in (%s) and taggables.taggable_type = ?) as laravel_table where laravel_row <= 11 order by laravel_row',
            [1, 2, 3],
            [Show::class],
        )], $this->readsOf('tags'));
    }

    #[Test]
    public function it_filters_on_a_morphed_by_many_relation(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/tags/search', ['search' => ['filters' => [['relation' => 'shows', 'nested' => [['field' => 'featured', 'value' => true]]]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
        $this->assertSame([[
            "select tags.id, tags.name from tags where (exists (select * from shows inner join taggables on shows.id{$text} = taggables.taggable_id where tags.id = taggables.tag_id and taggables.taggable_type = ? and (shows.featured = ?))) order by tags.id desc limit 50 offset 0",
            [Show::class, true],
        ]], $this->readsOf('tags'));
    }

    #[Test]
    public function it_includes_a_morphed_by_many_relation(): void
    {
        $response = $this->postJson('/api/tags/search', ['search' => ['includes' => [['relation' => 'shows'], ['relation' => 'stages']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [2, 1]);
        $this->assertSame([[2], [1]], self::nested($response->json('data'), 'shows', 'id'));
        $this->assertSame([[], ['main']], self::nested($response->json('data'), 'stages', 'code'));
    }

    #[Test]
    public function it_counts_through_morph_relations(): void
    {
        $response = $this->postJson('/api/tags/search', ['search' => ['aggregates' => [['relation' => 'shows.notes', 'type' => 'count']]]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [2, 1])
            ->assertJsonPath('data.*.shows_notes_count', [1, 2]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<mixed>>
     */
    private static function nested(array $rows, string $relation, string $key): array
    {
        return array_map(static fn (array $row): array => array_column($row[$relation], $key), $rows);
    }

    /**
     * @param  list<int>  $keys
     * @param  list<string>  $bindings
     * @return array{string, list<int|string>}
     */
    private static function eager(string $sql, array $keys, array $bindings): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [sprintf($sql, implode(', ', $keys)), $bindings];
        }

        return [sprintf($sql, implode(', ', array_fill(0, count($keys), '?'))), [...array_map(strval(...), $keys), ...$bindings]];
    }

    private static function text(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? '::text' : '';
    }
}
