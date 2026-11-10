<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AttachedPivotRowsTest extends HelpdeskTestCase
{
    use AssertsExactErrors;

    private const string ATTACHED = 'field holds a record that is already attached. Use update or sync to change its pivot.';

    private Ticket $jam;

    private Tag $hardware;

    private Tag $network;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->jam = $this->seedTickets()['Printer jam'];
        $this->hardware = Tag::create(['name' => 'Hardware']);
        $this->network = Tag::create(['name' => 'Network']);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_refuses_to_attach_a_row_that_is_already_attached(string $relation): void
    {
        $this->jam->{$relation}()->attach([$this->hardware->id => ['position' => 1]]);

        $response = $this->mutateTags($relation, [['operation' => 'attach', 'key' => [$this->network->id, $this->hardware->id], 'pivot' => ['position' => 2]]]);

        $this->assertExactErrors($response, ["mutate.0.relations.{$relation}.0.key" => ["The mutate.0.relations.{$relation}.0.key ".self::ATTACHED]]);
        $this->assertSame([[$this->hardware->id, 1]], $this->links($relation));
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_refuses_to_attach_a_row_that_an_entry_before_attached(string $relation): void
    {
        $response = $this->mutateTags($relation, [
            ['operation' => 'attach', 'key' => [$this->hardware->id]],
            ['operation' => 'attach', 'key' => [$this->hardware->id]],
        ]);

        $this->assertExactErrors($response, ["mutate.0.relations.{$relation}.1.key" => ["The mutate.0.relations.{$relation}.1.key ".self::ATTACHED]]);
        $this->assertSame([], $this->links($relation));
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_attaches_again_a_row_that_an_entry_before_detached(string $relation): void
    {
        $this->jam->{$relation}()->attach([$this->hardware->id => ['position' => 1]]);

        $this->mutateTags($relation, [
            ['operation' => 'detach', 'key' => [$this->hardware->id]],
            ['operation' => 'attach', 'key' => [$this->hardware->id], 'pivot' => ['position' => 2]],
        ])->assertOk();

        $this->assertSame([[$this->hardware->id, 2]], $this->links($relation));
    }

    /** @param list<array<string, mixed>> $entries */
    #[Test]
    #[DataProvider('attachesAfterUnlinking')]
    public function it_attaches_a_row_that_a_sync_or_a_toggle_before_detached(string $relation, array $entries): void
    {
        $this->jam->{$relation}()->attach([$this->hardware->id => ['position' => 1]]);
        $entries = array_map(fn (array $entry): array => [...$entry, 'key' => [$this->{$entry['key']}->id]], $entries);

        $this->mutateTags($relation, $entries)->assertOk();

        $this->assertSame([[$this->hardware->id, 2]], $this->links($relation));
    }

    /** @return iterable<string, array{string, list<array<string, mixed>>}> */
    public static function attachesAfterUnlinking(): iterable
    {
        foreach (self::relations() as $family => [$relation]) {
            yield $family.', sync' => [$relation, [['operation' => 'sync', 'key' => 'network'], ['operation' => 'detach', 'key' => 'network'], ['operation' => 'attach', 'key' => 'hardware', 'pivot' => ['position' => 2]]]];
            yield $family.', toggle' => [$relation, [['operation' => 'toggle', 'key' => 'hardware'], ['operation' => 'attach', 'key' => 'hardware', 'pivot' => ['position' => 2]]]];
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<int>  $links
     */
    #[Test]
    #[DataProvider('keptRows')]
    public function it_still_syncs_and_toggles_an_attached_row(string $relation, array $entry, array $links): void
    {
        $this->jam->{$relation}()->attach([$this->hardware->id => ['position' => 1]]);

        $this->mutateTags($relation, [[...$entry, 'key' => [$this->hardware->id]]])->assertOk();

        $this->assertSame(array_map(fn (int $position): array => [$this->hardware->id, $position], $links), $this->links($relation));
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>}> */
    public static function keptRows(): iterable
    {
        foreach (self::relations() as $family => [$relation]) {
            yield $family.', sync' => [$relation, ['operation' => 'sync', 'pivot' => ['position' => 2]], [2]];
            yield $family.', sync without detaching' => [$relation, ['operation' => 'sync', 'without_detaching' => true], [1]];
            yield $family.', toggle' => [$relation, ['operation' => 'toggle'], []];
        }
    }

    /** @return array<string, array{string}> */
    public static function relations(): array
    {
        return ['belongs to many' => ['tags'], 'morph to many' => ['labels']];
    }

    /** @param list<array<string, mixed>> $entries */
    private function mutateTags(string $relation, array $entries): TestResponse
    {
        return $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->jam->id, 'relations' => [$relation => $entries]],
        ]]);
    }

    /** @return list<array{mixed, mixed}> */
    private function links(string $relation): array
    {
        $table = $relation === 'tags' ? 'tag_ticket' : 'labelables';

        return DB::table($table)
            ->orderBy('tag_id')
            ->get(['tag_id', 'position'])
            ->map(fn (object $row): array => [$row->tag_id, $row->position])
            ->all();
    }
}
