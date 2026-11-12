<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DetachingTeamResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingTeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
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
                [['operation' => 'sync', 'key' => [101]], ['operation' => 'sync', 'key' => [101]]],
                [$at(1, 'operation') => ['The '.$at(1, 'operation').' field is prohibited: a relation takes one sync entry.']],
            ],
            'an attach next to a sync' => [
                [['operation' => 'sync', 'key' => [101]], ['operation' => 'attach', 'key' => [104]]],
                [$at(1, 'operation') => ['The '.$at(1, 'operation').' field must be create or update next to a sync entry.']],
            ],
            'a delete next to a sync' => [
                [['operation' => 'sync', 'key' => [101]], ['operation' => 'delete', 'key' => [102]]],
                [
                    $at(0, 'key') => ['The '.$at(0, 'key').' field leaves out a related row that another entry of the request names.'],
                    $at(1, 'operation') => ['The '.$at(1, 'operation').' field must be create or update next to a sync entry.'],
                ],
            ],
            'an update of a key that the sync does not list' => [
                [['operation' => 'sync', 'key' => [101]], ['operation' => 'update', 'key' => [101, 102], 'attributes' => ['status' => 'closed']]],
                [
                    $at(0, 'key') => ['The '.$at(0, 'key').' field leaves out a related row that another entry of the request names.'],
                    $at(1, 'key') => ['The '.$at(1, 'key').' field holds a key that the sync entry of the relation does not list.'],
                ],
            ],
            'attributes on a sync' => [
                [['operation' => 'sync', 'key' => [101], 'attributes' => ['status' => 'closed']]],
                [$at(0, 'attributes') => ['The '.$at(0, 'attributes').' field is prohibited when '.$at(0, 'operation').' is sync.']],
            ],
            'relations on a sync' => [
                [['operation' => 'sync', 'key' => [101], 'relations' => ['tags' => []]]],
                [$at(0, 'relations') => ['The '.$at(0, 'relations').' field is prohibited when '.$at(0, 'operation').' is sync.']],
            ],
            'the option of a sync that keeps the other rows' => [
                [['operation' => 'sync', 'key' => [101], 'without_detaching' => true]],
                [$at(0, 'without_detaching') => ['The '.$at(0, 'without_detaching').' field is prohibited.']],
            ],
            'a key that is not a child of the parent' => [
                [['operation' => 'sync', 'key' => [101, 104]]],
                [$at(0, 'key') => ['The selected '.$at(0, 'key').' is invalid.']],
            ],
        ];
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
        $tickets = [101 => 101, 102 => 101, 103 => 101, 104 => 102, $created->getKey() => $field->getKey()];
        ksort($tickets);

        $this->assertSame($tickets, self::tickets());
    }

    #[Test]
    public function it_refuses_a_sync_that_leaves_out_a_row_that_another_entry_names(): void
    {
        $this->assertRefused(['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101]]]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => [102]]]]],
        ]], [self::KEY => ['The '.self::KEY.' field leaves out a related row that another entry of the request names.']]);
    }

    #[Test]
    public function it_takes_a_row_that_another_entry_names_when_the_sync_lists_it(): void
    {
        $response = $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101, 102]]]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'update', 'key' => [104], 'attributes' => ['status' => 'closed']]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 102 => 101, 104 => 102], self::tickets());
    }

    #[Test]
    public function it_refuses_a_sync_by_detach_whose_foreign_key_does_not_accept_null(): void
    {
        $this->app?->bind(TeamResource::class, DetachingTeamResource::class);

        $this->assertRefused(['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [104]]]]],
        ]], [self::KEY => ['The '.self::KEY.' field is prohibited: this relation cannot be detached, its link column does not accept null.']]);
    }

    #[Test]
    public function it_refuses_a_sync_that_removes_more_rows_than_the_total_of_entries(): void
    {
        config(['rest.limits.totals.entries' => 3]);
        Ticket::query()->insert([['id' => 105, 'team_id' => 101, 'title' => 'No sound', 'status' => 'open'], ['id' => 106, 'team_id' => 101, 'title' => 'Dead mouse', 'status' => 'open']]);
        $message = 'The '.self::KEY.' field leaves out more than 3 related rows, the most that a sync removes.';

        $response = $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['name' => 'Helpdesk'], 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101]]]]],
        ]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [self::KEY => [$message]]]);
        $this->assertSame('Support', Team::query()->findOrFail(101)->getAttribute('name'));
        $this->assertSame([101 => 101, 102 => 101, 103 => 101, 104 => 102, 105 => 101, 106 => 101], self::tickets());
    }

    #[Test]
    public function it_syncs_a_relation_that_removes_as_many_rows_as_the_total_of_entries(): void
    {
        config(['rest.limits.totals.entries' => 3]);
        Ticket::query()->insert(['id' => 105, 'team_id' => 101, 'title' => 'No sound', 'status' => 'open']);

        $response = $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 104 => 102], self::tickets());
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
