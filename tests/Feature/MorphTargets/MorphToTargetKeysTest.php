<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\MorphTargets\Stubs\KeyedNoteResource;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\Note;
use Lomkit\Rest\Tests\Fixtures\Festival\NoteResource;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToTargetKeysTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Festival];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['show' => Show::class, 'stage' => Stage::class], false);
        $this->app?->bind(NoteResource::class, KeyedNoteResource::class);
        Stage::query()->create(['code' => 'main', 'name' => 'Main']);
        Stage::query()->create(['code' => 'side', 'name' => 'Side']);
        Show::query()->create(['id' => 1, 'title' => 'Opening night', 'starts_on' => '2026-07-01', 'status' => 'planned']);
        Show::query()->create(['id' => 2, 'title' => 'Closing night', 'starts_on' => '2026-07-02', 'status' => 'planned']);
        Note::query()->create(['id' => 1, 'notable_type' => 'show', 'notable_id' => '1', 'body' => 'Sound check']);
        Note::query()->create(['id' => 2, 'notable_type' => 'stage', 'notable_id' => 'main', 'body' => 'Lights']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $answer
     * @param  list<array{int, string, string, string}>  $notes
     * @param  list<array{string, string}>  $stages
     * @param  list<array{int, string}>  $shows
     */
    #[Test]
    #[DataProvider('switchedTargets')]
    public function it_writes_the_key_of_the_type_that_the_entry_names(int $note, array $entry, array $answer, array $notes, array $stages, array $shows): void
    {
        $response = $this->postJson('/api/notes/mutate', ['mutate' => [['operation' => 'update', 'key' => $note, 'relations' => ['notable' => $entry]]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => $note, 'relations' => ['notable' => $answer]]]]);
        $this->assertSame($notes, self::notes());
        $this->assertSame($stages, self::stages());
        $this->assertSame($shows, self::shows());
    }

    /** @return array<string, array{int, array<string, mixed>, array<string, mixed>, list<array{int, string, string, string}>, list<array{string, string}>, list<array{int, string}>}> */
    public static function switchedTargets(): array
    {
        $stages = [['main', 'Main'], ['side', 'Side']];
        $shows = [[1, 'Opening night'], [2, 'Closing night']];

        return [
            'an attach from a show to a stage' => [
                1,
                ['operation' => 'attach', 'type' => 'stage', 'key' => 'side'],
                ['operation' => 'attach', 'type' => 'stage', 'key' => 'side'],
                [[1, 'stage', 'side', 'Sound check'], [2, 'stage', 'main', 'Lights']],
                $stages,
                $shows,
            ],
            'an attach from a stage to a show' => [
                2,
                ['operation' => 'attach', 'type' => 'show', 'key' => 2],
                ['operation' => 'attach', 'type' => 'show', 'key' => 2],
                [[1, 'show', '1', 'Sound check'], [2, 'show', '2', 'Lights']],
                $stages,
                $shows,
            ],
            'an update from a show to a stage' => [
                1,
                ['operation' => 'update', 'type' => 'stage', 'key' => 'side', 'attributes' => ['name' => 'Garden']],
                ['operation' => 'update', 'type' => 'stage', 'key' => 'side'],
                [[1, 'stage', 'side', 'Sound check'], [2, 'stage', 'main', 'Lights']],
                [['main', 'Main'], ['side', 'Garden']],
                $shows,
            ],
            'an update from a stage to a show' => [
                2,
                ['operation' => 'update', 'type' => 'show', 'key' => 2, 'attributes' => ['title' => 'Last night']],
                ['operation' => 'update', 'type' => 'show', 'key' => 2],
                [[1, 'show', '1', 'Sound check'], [2, 'show', '2', 'Lights']],
                $stages,
                [[1, 'Opening night'], [2, 'Last night']],
            ],
            'a create from a show to a stage' => [
                1,
                ['operation' => 'create', 'type' => 'stage', 'attributes' => ['code' => 'tent', 'name' => 'Tent']],
                ['operation' => 'create', 'type' => 'stage', 'key' => 'tent'],
                [[1, 'stage', 'tent', 'Sound check'], [2, 'stage', 'main', 'Lights']],
                [['main', 'Main'], ['side', 'Side'], ['tent', 'Tent']],
                $shows,
            ],
            'a create from a stage to a show' => [
                2,
                ['operation' => 'create', 'type' => 'show', 'attributes' => ['id' => 3, 'title' => 'Encore', 'starts_on' => '2026-07-03', 'status' => 'planned']],
                ['operation' => 'create', 'type' => 'show', 'key' => 3],
                [[1, 'show', '1', 'Sound check'], [2, 'show', '3', 'Lights']],
                $stages,
                [[1, 'Opening night'], [2, 'Closing night'], [3, 'Encore']],
            ],
        ];
    }

    /** @param list<array{int, string, string, string}> $notes */
    #[Test]
    #[DataProvider('createdNotes')]
    public function it_writes_the_key_of_the_type_that_the_entry_names_on_a_created_row(array $entry, array $notes): void
    {
        $response = $this->postJson('/api/notes/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['id' => 3, 'body' => 'Doors'], 'relations' => ['notable' => $entry]]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => 3, 'relations' => ['notable' => $entry]]]]);
        $this->assertSame($notes, self::notes());
    }

    /** @return array<string, array{array<string, mixed>, list<array{int, string, string, string}>}> */
    public static function createdNotes(): array
    {
        $notes = [[1, 'show', '1', 'Sound check'], [2, 'stage', 'main', 'Lights']];

        return [
            'a stage' => [['operation' => 'attach', 'type' => 'stage', 'key' => 'side'], [...$notes, [3, 'stage', 'side', 'Doors']]],
            'a show' => [['operation' => 'attach', 'type' => 'show', 'key' => 2], [...$notes, [3, 'show', '2', 'Doors']]],
        ];
    }

    /** @return list<array{int, string, string, string}> */
    private static function notes(): array
    {
        return DB::table('notes')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [(int) $row->id, $row->notable_type, $row->notable_id, $row->body])
            ->all();
    }

    /** @return list<array{string, string}> */
    private static function stages(): array
    {
        return DB::table('stages')
            ->orderBy('code')
            ->get()
            ->map(static fn (object $row): array => [$row->code, $row->name])
            ->all();
    }

    /** @return list<array{int, string}> */
    private static function shows(): array
    {
        return DB::table('shows')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [(int) $row->id, $row->title])
            ->all();
    }
}
