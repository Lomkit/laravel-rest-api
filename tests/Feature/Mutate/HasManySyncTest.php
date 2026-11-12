<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DeleteRefusingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterSyncingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedTeamResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingTeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use PHPUnit\Framework\Attributes\Test;

class HasManySyncTest extends WriteTraceTestCase
{
    private const string MESSAGE = 'The relation has a related row that the sync neither lists nor removes. Read the relation again, without include limits or filters, then send the request again.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app?->bind(TeamResource::class, SyncingTeamResource::class);
        $this->app?->bind(BookResource::class, SyncingBookResource::class);
    }

    #[Test]
    public function it_writes_the_entries_next_to_a_sync_that_accounts_for_every_child(): void
    {
        Ticket::query()->insert(['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'open']);
        Event::fake([ResourceMutated::class]);
        TracingPolicy::$trace = [];

        $this->assertWrites('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
                ['operation' => 'sync', 'key' => [101]],
                ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']],
                ['operation' => 'delete', 'key' => [102, 103]],
                ['operation' => 'create', 'attributes' => ['title' => 'Broken screen', 'status' => 'open']],
            ]]],
        ]], [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
                ['operation' => 'sync', 'key' => [101]],
                ['operation' => 'update', 'key' => 101],
                ['operation' => 'delete', 'key' => [102, 103]],
                ['operation' => 'create', 'key' => 'Ticket(Broken screen)'],
            ]]],
        ], [
            'policy viewAny',
            'policy create',
            'sql select * from teams where teams.id in (101)',
            'sql select * from tickets where tickets.id in (101, 102, 103)',
            self::locked('sql select id from teams where teams.id = ?'),
            self::locked('sql select * from tickets where tickets.team_id = ? and tickets.team_id is not null and tickets.id not in (?, ?, ?) order by tickets.id asc limit 1'),
            'policy update Team(Support)',
            'policy view Ticket(Printer jam)',
            'policy update Ticket(Printer jam)',
            'policy delete Ticket(Password reset)',
            'policy delete Ticket(Slow laptop)',
            'policy create',
            'saved Team(Support)',
            'sql update tickets set status = ?, updated_at = ? where id = ?',
            'saved Ticket(Printer jam)',
            'sql delete from tickets where id = ?',
            'sql delete from tickets where id = ?',
            'sql insert into tickets (title, status, team_id, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Ticket(Broken screen)',
        ]);
        $this->assertSame(['Ticket(Broken screen) open [] []', 'Ticket(Printer jam) closed [Tag(Hardware) 1] []'], self::rows('tickets'));
        Event::assertDispatched(ResourceMutated::class, 1);
    }

    #[Test]
    public function it_refuses_a_sync_of_the_rows_that_an_include_returned_when_the_relation_has_more(): void
    {
        $this->seedTickets(103, 112);
        $tickets = self::tickets();
        $sync = ['operation' => 'sync', 'key' => range(101, 110)];
        $update = ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']];

        $this->assertConflict($this->syncTickets(101, [$sync, $update]), 'mutate.0.relations.tickets.0.key', 111);
        $this->assertSame($tickets, self::tickets());

        $this->syncTickets(101, [$sync, $update, ['operation' => 'delete', 'key' => [111, 112]]])->assertOk();
        $this->assertSame(range(101, 110), array_keys(self::tickets()));
        $this->assertSame('closed', Ticket::query()->findOrFail(101)->getAttribute('status'));
    }

    #[Test]
    public function it_refuses_a_sync_of_a_list_that_a_child_added_since_the_read_makes_stale(): void
    {
        $read = array_keys(self::tickets());
        Ticket::query()->insert(['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'open']);

        $this->assertConflict($this->syncTickets(101, [['operation' => 'sync', 'key' => $read], ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']]]), 'mutate.0.relations.tickets.0.key', 103);
        $this->assertSame('open', Ticket::query()->findOrFail(101)->getAttribute('status'));
    }

    #[Test]
    public function it_refuses_a_sync_of_a_list_that_the_filter_of_the_client_left_a_visible_child_out_of(): void
    {
        Ticket::query()->insert(['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'closed']);
        $open = Ticket::query()
            ->where('status', 'open')
            ->pluck('id')
            ->all();

        $this->assertConflict($this->syncTickets(101, [['operation' => 'sync', 'key' => $open]]), 'mutate.0.relations.tickets.0.key', 103);
        $this->assertSame([101 => 101, 102 => 101, 103 => 101], self::tickets());
    }

    #[Test]
    public function it_writes_nothing_when_the_policy_refuses_a_delete_next_to_a_sync(): void
    {
        Gate::policy(Ticket::class, DeleteRefusingPolicy::class);

        $response = $this->syncTickets(101, [
            ['operation' => 'sync', 'key' => [101]],
            ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']],
            ['operation' => 'delete', 'key' => [102]],
        ]);

        $response->assertForbidden();
        $this->assertSame([101 => 101, 102 => 101], self::tickets());
        $this->assertSame('open', Ticket::query()->findOrFail(101)->getAttribute('status'));
    }

    #[Test]
    public function it_checks_a_sync_nested_under_another_relation(): void
    {
        $nested = fn (array $keys): array => ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['team' => ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => $keys]]]]]],
        ]];

        $this->assertConflict($this->postJson('/api/tickets/mutate', $nested([101])), 'mutate.0.relations.team.relations.tickets.0.key', 102);

        $response = $this->postJson('/api/tickets/mutate', $nested([101, 102]));

        $response->assertOk();
        $this->assertSame([['operation' => 'sync', 'key' => [101, 102]]], $response->json('operations.0.relations.team.relations.tickets'));
    }

    #[Test]
    public function it_checks_each_sync_of_the_same_parent_in_one_request(): void
    {
        $entry = fn (array $keys, int $ticket): array => ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
            ['operation' => 'sync', 'key' => $keys],
            ['operation' => 'update', 'key' => $ticket, 'attributes' => ['status' => 'closed']],
        ]]];

        $this->postJson('/api/teams/mutate', ['mutate' => [$entry([101, 102], 101), $entry([101, 102], 102)]])->assertOk();
        $this->assertSame(['closed', 'closed'], Ticket::query()->orderBy('id')->pluck('status')->all());

        $response = $this->postJson('/api/teams/mutate', ['mutate' => [$entry([101, 102], 101), $entry([102], 102)]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['mutate.1.relations.tickets.0.key' => ['The mutate.1.relations.tickets.0.key field neither lists nor removes a related row that another entry of the request names.']]);
    }

    #[Test]
    public function it_detaches_a_morph_many_child_next_to_a_sync_and_leaves_a_row_of_another_type_untouched(): void
    {
        Label::query()->insert([
            ['id' => 103, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'rare'],
            ['id' => 104, 'labelable_type' => Shelf::class, 'labelable_id' => 101, 'text' => 'shelved'],
        ]);
        TracingPolicy::$trace = [];

        $this->assertWrites('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]], ['operation' => 'detach', 'key' => [103]]]]],
        ]], [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]], ['operation' => 'detach', 'key' => [103]]]]],
        ], [
            'policy viewAny',
            'sql select * from books where books.id in (101)',
            'sql select * from labels where labels.id in (101, 103)',
            self::locked('sql select id from books where books.id = ?'),
            self::locked('sql select * from labels where labels.labelable_type = ? and labels.labelable_id = ? and labels.labelable_id is not null and labels.id not in (?, ?) order by labels.id asc limit 1'),
            'policy update Book(Dune)',
            'policy view Label(classic)',
            'policy update Label(rare)',
            'saved Book(Dune)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(rare)',
        ]);
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) - -', 'Label(rare) - -', 'Label(shelved) Shelf Shelf(Classics)'], self::rows('labels'));
    }

    #[Test]
    public function it_leaves_out_of_the_check_the_children_outside_the_perimeter(): void
    {
        $this->app?->bind(BookResource::class, PerimeterSyncingBookResource::class);
        Label::query()->insert(['id' => 103, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'Hidden']);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 102 => null, 103 => 101], Label::query()
            ->orderBy('id')
            ->pluck('labelable_id', 'id')
            ->all());
    }

    #[Test]
    public function it_keeps_the_sync_of_a_custom_relation_that_lists_it_as_a_link(): void
    {
        $this->app?->bind(TeamResource::class, SyncedTeamResource::class);
        Ticket::query()->insert(['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'open']);

        $response = $this->syncTickets(101, [['operation' => 'sync', 'key' => [101]]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 102 => 101, 103 => 101], self::tickets());
    }

    /** @param list<array<string, mixed>> $entries */
    private function syncTickets(int $team, array $entries): TestResponse
    {
        return $this->postJson('/api/teams/mutate', ['mutate' => [['operation' => 'update', 'key' => $team, 'relations' => ['tickets' => $entries]]]]);
    }

    private function seedTickets(int $from, int $to): void
    {
        foreach (range($from, $to) as $id) {
            Ticket::query()->insert(['id' => $id, 'team_id' => 101, 'title' => "Ticket {$id}", 'status' => 'open']);
        }
    }

    private function assertConflict(TestResponse $response, string $path, int $key): void
    {
        $response->assertConflict()->assertExactJson(['message' => self::MESSAGE, 'conflicts' => [['path' => $path, 'key' => $key]]]);
    }

    /** @return array<int, int> */
    private static function tickets(): array
    {
        return Ticket::query()
            ->orderBy('id')
            ->pluck('team_id', 'id')
            ->all();
    }

    private static function locked(string $line): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? $line : $line.' for update';
    }
}
