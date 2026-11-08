<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Festival;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Note;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Show;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Festival\Stage;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class MorphToKeyTypesTest extends PackageTestCase
{
    use CapturesQueries;

    private const string TARGETS = '(notes.notable_type = ? and exists (select * from shows where notes.notable_id = shows.id%1$s and (shows.featured = ?))) or (notes.notable_type = ? and exists (select * from stages where notes.notable_id = stages.code%1$s and (stages.code = ?)))';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Festival, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Show::create(['id' => 1, 'title' => 'Opening', 'featured' => true]);
        Show::create(['id' => 2, 'title' => 'Closing', 'featured' => false]);
        Stage::create(['code' => 'main', 'name' => 'Main']);
        Stage::create(['code' => 'side', 'name' => 'Side']);
        Note::create(['id' => 1, 'body' => 'Sound check', 'notable_type' => Show::class, 'notable_id' => '1']);
        Note::create(['id' => 2, 'body' => 'Late start', 'notable_type' => Show::class, 'notable_id' => '2']);
        Note::create(['id' => 3, 'body' => 'Wet floor', 'notable_type' => Stage::class, 'notable_id' => 'main']);
        Note::create(['id' => 4, 'body' => 'Spare cables', 'notable_type' => Stage::class, 'notable_id' => 'side']);
        Note::create(['id' => 5, 'body' => 'Loose note']);
    }

    #[Test]
    public function it_filters_on_targets_with_integer_and_string_keys(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/notes/search', ['search' => ['filters' => [['relation' => 'notable', 'types' => self::types()]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 1]);
        $this->assertSame([[
            'select notes.id, notes.body from notes where (('.self::targets().')) order by notes.id desc limit 50 offset 0',
            [Show::class, true, Stage::class, 'main'],
        ]], $this->readsOf('notes'));
    }

    #[Test]
    public function it_filters_on_rows_without_a_matching_target(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/notes/search', ['search' => ['filters' => [['relation' => 'notable', 'exists' => false, 'types' => self::types()]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [5, 4, 2]);
        $this->assertSame([[
            'select notes.id, notes.body from notes where ((not (('.self::targets().')) or notes.notable_type is null)) order by notes.id desc limit 50 offset 0',
            [Show::class, true, Stage::class, 'main'],
        ]], $this->readsOf('notes'));
    }

    #[Test]
    public function it_filters_on_rows_with_any_target(): void
    {
        $text = self::text();
        $this->captureQueries();

        $response = $this->postJson('/api/notes/search', ['search' => ['filters' => [['relation' => 'notable']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [4, 3, 2, 1]);
        $this->assertSame([[
            "select notes.id, notes.body from notes where (((notes.notable_type = ? and exists (select * from shows where notes.notable_id = shows.id{$text})) or (notes.notable_type = ? and exists (select * from stages where notes.notable_id = stages.code{$text})))) order by notes.id desc limit 50 offset 0",
            [Show::class, Stage::class],
        ]], $this->readsOf('notes'));
    }

    #[Test]
    #[WithConfig('rest.limits.depths.filters', 2)]
    public function it_filters_on_the_targets_inside_a_nested_group(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/stages/search', ['search' => ['filters' => [['relation' => 'notes', 'nested' => [['relation' => 'notable', 'types' => self::types()]]]]]]);

        $response->assertOk()->assertJsonPath('data.*.code', ['main']);
        $this->assertSame([[
            'select stages.code, stages.name from stages where (exists (select * from notes where stages.code = notes.notable_id and notes.notable_type = ? and (('.self::targets().')))) order by stages.code desc limit 50 offset 0',
            [Stage::class, Show::class, true, Stage::class, 'main'],
        ]], $this->readsOf('stages'));
    }

    #[Test]
    public function it_filters_on_the_targets_of_included_rows(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/stages/search', ['search' => ['includes' => [['relation' => 'notes', 'filters' => [['relation' => 'notable', 'types' => self::types()]]]]]]);

        $response->assertOk()->assertJsonPath('data.*.notes.*.id', [3]);
        $this->assertSame([[
            'select * from (select notes.id, notes.body, notes.notable_id, notes.notable_type, row_number() over (partition by notes.notable_id order by notes.id desc) as laravel_row from notes where notes.notable_id in (?, ?) and notes.notable_type = ? and (('.self::targets().'))) as laravel_table where laravel_row <= 11 order by laravel_row',
            ['main', 'side', Stage::class, Show::class, true, Stage::class, 'main'],
        ]], $this->readsOf('notes'));
    }

    #[Test]
    public function it_keeps_the_key_comparison_of_targets_with_one_key_type(): void
    {
        Book::create(['id' => 1, 'title' => 'Dune']);
        Label::create(['id' => 1, 'text' => 'Classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);
        $this->captureQueries();

        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => [['relation' => 'labelable', 'types' => [Book::class => [['field' => 'title', 'value' => 'Dune']]]]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
        $this->assertSame([[
            'select labels.id, labels.text, labels.labelable_id, labels.labelable_type from labels where (((labels.labelable_type = ? and exists (select * from books where labels.labelable_id = books.id and (books.title = ?))))) order by labels.id desc limit 50 offset 0',
            [Book::class, 'Dune'],
        ]], $this->readsOf('labels'));
    }

    /** @return array<string, list<array{field: string, value: mixed}>> */
    private static function types(): array
    {
        return [
            Show::class => [['field' => 'featured', 'value' => true]],
            Stage::class => [['field' => 'code', 'value' => 'main']],
        ];
    }

    private static function targets(): string
    {
        return sprintf(self::TARGETS, self::text());
    }

    private static function text(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? '::text' : '';
    }
}
