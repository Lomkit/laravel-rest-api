<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingTeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class HasManySyncValidationTest extends WriteTraceTestCase
{
    use AssertsExactErrors;

    private const string KEY = 'mutate.0.relations.tickets.0.key';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app?->bind(TeamResource::class, SyncingTeamResource::class);
        Team::query()->insert(['id' => 102, 'name' => 'Ops']);
        Ticket::query()->insert([
            ['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'open'],
            ['id' => 104, 'team_id' => 102, 'title' => 'Lost badge', 'status' => 'open'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedEntries')]
    public function it_refuses_an_entry_that_a_sync_does_not_take(array $entries, array $errors): void
    {
        $this->assertRefused(['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => $entries]]]], $errors);
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function refusedEntries(): array
    {
        $at = fn (int $index, string $key): string => "mutate.0.relations.tickets.{$index}.{$key}";

        return [
            'a second sync' => [
                [['operation' => 'sync', 'key' => [101, 102, 103]], ['operation' => 'sync', 'key' => [101, 102, 103]]],
                [$at(1, 'operation') => ['The '.$at(1, 'operation').' field is prohibited: a relation takes one sync entry.']],
            ],
            'an attach next to a sync' => [
                [['operation' => 'sync', 'key' => [101, 102, 103]], ['operation' => 'attach', 'key' => [104]]],
                [$at(1, 'operation') => ['The '.$at(1, 'operation').' field must be create, update, delete or detach next to a sync entry.']],
            ],
            'a delete of a key that the sync lists' => [
                [['operation' => 'sync', 'key' => [101, 102, 103]], ['operation' => 'delete', 'key' => [103]]],
                [$at(1, 'key') => ['The '.$at(1, 'key').' field removes or moves a related row that a sync entry of its parent keeps.']],
            ],
            'an update of a key that the sync does not list' => [
                [['operation' => 'sync', 'key' => [101, 103]], ['operation' => 'update', 'key' => [101, 102], 'attributes' => ['status' => 'closed']]],
                [
                    $at(0, 'key') => ['The '.$at(0, 'key').' field neither lists nor removes a related row that another entry of the request names.'],
                    $at(1, 'key') => ['The '.$at(1, 'key').' field holds a key that the sync entry of the relation does not list.'],
                ],
            ],
            'attributes on a sync' => [
                [['operation' => 'sync', 'key' => [101, 102, 103], 'attributes' => ['status' => 'closed']]],
                [$at(0, 'attributes') => ['The '.$at(0, 'attributes').' field is prohibited when '.$at(0, 'operation').' is sync.']],
            ],
            'relations on a sync' => [
                [['operation' => 'sync', 'key' => [101, 102, 103], 'relations' => ['tags' => []]]],
                [$at(0, 'relations') => ['The '.$at(0, 'relations').' field is prohibited when '.$at(0, 'operation').' is sync.']],
            ],
            'the option of a sync that keeps the other rows' => [
                [['operation' => 'sync', 'key' => [101, 102, 103], 'without_detaching' => true]],
                [$at(0, 'without_detaching') => ['The '.$at(0, 'without_detaching').' field is prohibited.']],
            ],
            'a detach on a foreign key that does not accept null' => [
                [['operation' => 'sync', 'key' => [101, 103]], ['operation' => 'detach', 'key' => [102]]],
                [$at(1, 'key') => ['The '.$at(1, 'key').' field is prohibited: this relation cannot be detached, its link column does not accept null.']],
            ],
            'more keys than a list holds' => [
                [['operation' => 'sync', 'key' => range(1, 101)]],
                [$at(0, 'key') => ['The '.$at(0, 'key').' field must not have more than 100 items.']],
            ],
            'a key that is not a child of the parent' => [
                [['operation' => 'sync', 'key' => [101, 102, 103, 104]]],
                [$at(0, 'key') => ['The selected '.$at(0, 'key').' is invalid.']],
            ],
        ];
    }

    #[Test]
    public function it_refuses_a_detach_of_a_key_that_the_sync_lists(): void
    {
        $this->app?->bind(BookResource::class, SyncingBookResource::class);
        $labels = Label::query()
            ->orderBy('id')
            ->pluck('labelable_id', 'id')
            ->all();

        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]], ['operation' => 'detach', 'key' => [101]]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.labels.1.key' => ['The mutate.0.relations.labels.1.key field removes or moves a related row that a sync entry of its parent keeps.']]);
        $this->assertSame($labels, Label::query()->orderBy('id')->pluck('labelable_id', 'id')->all());
    }

    /** @param array<string, mixed> $other */
    #[Test]
    #[DataProvider('contradictingEntries')]
    public function it_refuses_an_entry_that_removes_or_moves_a_row_that_a_sync_of_its_parent_keeps(array $other): void
    {
        $this->assertRefused(['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101, 102, 103]]]]],
            $other,
        ]], ['mutate.1.relations.tickets.0.key' => ['The mutate.1.relations.tickets.0.key field removes or moves a related row that a sync entry of its parent keeps.']]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function contradictingEntries(): array
    {
        return [
            'a delete in another list of the parent' => [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => [103]]]]]],
            'an attach under another parent' => [['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => [103]]]]]],
        ];
    }

    #[Test]
    public function it_refuses_a_detach_in_another_list_of_the_parent_of_a_row_that_a_sync_keeps(): void
    {
        $this->app?->bind(BookResource::class, SyncingBookResource::class);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]]]]],
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'detach', 'key' => [101]]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.1.relations.labels.0.key' => ['The mutate.1.relations.labels.0.key field removes or moves a related row that a sync entry of its parent keeps.']]);
        $this->assertSame(101, Label::query()->findOrFail(101)->getAttribute('labelable_id'));
    }

    #[Test]
    public function it_refuses_a_sync_under_a_parent_created_in_the_request_unless_it_lists_no_key(): void
    {
        $this->assertRefused(['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Field'], 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101]]]]],
        ]], [self::KEY => ['The selected '.self::KEY.' is invalid.']]);

        $response = $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Field'], 'relations' => ['tickets' => [
                ['operation' => 'sync', 'key' => []],
                ['operation' => 'create', 'attributes' => ['title' => 'Broken screen', 'status' => 'open']],
            ]]],
        ]]);

        $response->assertOk();
        $field = Team::query()
            ->where('name', 'Field')
            ->sole();
        $created = Ticket::query()
            ->where('title', 'Broken screen')
            ->sole();

        $this->assertSame($field->getKey(), $created->getAttribute('team_id'));
    }

    #[Test]
    public function it_refuses_a_sync_that_neither_lists_nor_removes_a_row_that_another_entry_names(): void
    {
        $this->assertRefused(['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101, 103]]]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => [102]]]]],
        ]], [self::KEY => ['The '.self::KEY.' field neither lists nor removes a related row that another entry of the request names.']]);
    }

    #[Test]
    public function it_takes_a_row_that_another_entry_names_when_the_sync_lists_it(): void
    {
        $response = $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101, 102]], ['operation' => 'delete', 'key' => [103]]]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'update', 'key' => [104], 'attributes' => ['status' => 'closed']]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 102 => 101, 104 => 102], self::tickets());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $errors
     */
    private function assertRefused(array $payload, array $errors): void
    {
        $tickets = self::tickets();

        $this->assertExactErrors($this->postJson('/api/teams/mutate', $payload), $errors);
        $this->assertSame($tickets, self::tickets());
    }

    /** @return array<int, int> */
    private static function tickets(): array
    {
        return Ticket::query()
            ->orderBy('id')
            ->pluck('team_id', 'id')
            ->all();
    }
}
