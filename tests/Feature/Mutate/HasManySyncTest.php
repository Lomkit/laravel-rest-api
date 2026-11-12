<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterSyncingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedTeamResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingTeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use PHPUnit\Framework\Attributes\Test;

class HasManySyncTest extends WriteTraceTestCase
{
    #[Test]
    public function it_deletes_the_children_that_a_sync_leaves_out_and_writes_the_entries_next_to_it(): void
    {
        $this->app?->bind(TeamResource::class, SyncingTeamResource::class);
        Ticket::query()->insert(['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'open']);
        Event::fake([ResourceMutated::class]);
        Event::listen('eloquent.deleted: '.Ticket::class, function (Ticket $ticket): void {
            TracingPolicy::$trace[] = 'deleted '.TracingPolicy::name($ticket);
        });
        TracingPolicy::$trace = [];

        $this->assertWrites('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
                ['operation' => 'sync', 'key' => [101]],
                ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']],
                ['operation' => 'create', 'attributes' => ['title' => 'Broken screen', 'status' => 'open']],
            ]]],
        ]], [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
                ['operation' => 'sync', 'key' => [101]],
                ['operation' => 'update', 'key' => 101],
                ['operation' => 'create', 'key' => 'Ticket(Broken screen)'],
            ]]],
        ], [
            'policy viewAny',
            'policy create',
            'sql select * from teams where teams.id in (101)',
            'sql select * from tickets where tickets.id in (101)',
            self::locked('sql select id from teams where teams.id = ?'),
            self::locked('sql select * from tickets where tickets.team_id = ? and tickets.team_id is not null and tickets.id not in (?) order by tickets.id asc limit 1001'),
            'policy update Team(Support)',
            'policy view Ticket(Printer jam)',
            'policy delete Ticket(Password reset)',
            'policy delete Ticket(Slow laptop)',
            'policy update Ticket(Printer jam)',
            'policy create',
            'saved Team(Support)',
            'sql delete from tickets where id = ?',
            'deleted Ticket(Password reset)',
            'sql delete from tickets where id = ?',
            'deleted Ticket(Slow laptop)',
            'sql update tickets set status = ?, updated_at = ? where id = ?',
            'saved Ticket(Printer jam)',
            'sql insert into tickets (title, status, team_id, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Ticket(Broken screen)',
        ]);
        $this->assertSame(['Ticket(Broken screen) open [] []', 'Ticket(Printer jam) closed [Tag(Hardware) 1] []'], self::rows('tickets'));
        Event::assertDispatched(ResourceMutated::class, 1);
    }

    #[Test]
    public function it_sets_the_link_columns_of_the_children_that_a_sync_leaves_out_to_null(): void
    {
        $this->app?->bind(BookResource::class, SyncingBookResource::class);
        Label::query()->insert(['id' => 103, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'rare']);
        TracingPolicy::$trace = [];

        $this->assertWrites('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]]]]],
        ]], [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]]]]],
        ], [
            'policy viewAny',
            'sql select * from books where books.id in (101)',
            'sql select * from labels where labels.id in (101)',
            self::locked('sql select id from books where books.id = ?'),
            self::locked('sql select * from labels where labels.labelable_type = ? and labels.labelable_id = ? and labels.labelable_id is not null and labels.id not in (?) order by labels.id asc limit 1001'),
            'policy update Book(Dune)',
            'policy view Label(classic)',
            'policy update Label(rare)',
            'saved Book(Dune)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(rare)',
        ]);
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) - -', 'Label(rare) - -'], self::rows('labels'));
    }

    #[Test]
    public function it_leaves_the_children_outside_the_perimeter_untouched(): void
    {
        $this->app?->bind(BookResource::class, PerimeterSyncingBookResource::class);
        Label::query()->insert([
            ['id' => 103, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'Hidden'],
            ['id' => 104, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'rare'],
        ]);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 102 => null, 103 => 101, 104 => null], Label::query()
            ->orderBy('id')
            ->pluck('labelable_id', 'id')
            ->all());
    }

    #[Test]
    public function it_keeps_the_sync_of_a_custom_relation_that_lists_it_as_a_link(): void
    {
        $this->app?->bind(TeamResource::class, SyncedTeamResource::class);
        Ticket::query()->insert(['id' => 103, 'team_id' => 101, 'title' => 'Slow laptop', 'status' => 'open']);

        $response = $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'sync', 'key' => [101]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101, 102, 103], Ticket::query()->where('team_id', 101)->orderBy('id')->pluck('id')->all());
    }

    private static function locked(string $line): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? $line : $line.' for update';
    }
}
