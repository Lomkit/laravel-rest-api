<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class AttachedRowReadsTest extends WriteTraceTestCase
{
    private const string ATTACHED = 'select tags.*, tag_ticket.ticket_id as pivot_ticket_id, tag_ticket.tag_id as pivot_tag_id, tag_ticket.position as pivot_position, tag_ticket.weight as pivot_weight, tag_ticket.added_by as pivot_added_by, tag_ticket.note as pivot_note, tag_ticket.created_at as pivot_created_at from tags inner join tag_ticket on tags.id = tag_ticket.tag_id where tag_ticket.ticket_id = ?';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Tag::query()->insert([
            ['id' => 103, 'name' => 'Urgent', 'deleted_at' => null],
            ['id' => 104, 'name' => 'Archived', 'deleted_at' => '2026-01-01 00:00:00'],
            ['id' => 105, 'name' => 'Billing', 'deleted_at' => null],
            ['id' => 106, 'name' => 'Login', 'deleted_at' => null],
        ]);
        DB::table('tag_ticket')->insert([
            ['tag_id' => 104, 'ticket_id' => 101, 'position' => 4],
            ['tag_id' => 105, 'ticket_id' => 101, 'position' => 5],
        ]);
        TracingPolicy::$trace = [];
    }

    #[Test]
    public function it_reads_only_the_named_rows_for_a_toggle(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([['operation' => 'toggle', 'key' => [101, 102]]]), [
            'select * from tickets where tickets.id in (101)',
            'select * from tags where tags.id in (101, 102) and tags.deleted_at is null',
            self::ATTACHED.' and tags.id in (?, ?) and tags.deleted_at is null',
            'select tag_id from tag_ticket where tag_ticket.ticket_id = ?',
            'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?)',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
        ]);
        $this->assertSame([102, 104, 105], self::attached());
    }

    #[Test]
    public function it_reads_only_the_named_rows_for_a_sync_without_detaching(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([['operation' => 'sync', 'key' => [102, 103], 'without_detaching' => true]]), [
            'select * from tickets where tickets.id in (101)',
            'select * from tags where tags.id in (102, 103) and tags.deleted_at is null',
            self::ATTACHED.' and tags.id in (?, ?) and tags.deleted_at is null',
            'select * from tag_ticket where tag_ticket.ticket_id = ?',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
        ]);
        $this->assertSame([101, 102, 103, 104, 105], self::attached());
    }

    #[Test]
    public function it_refuses_a_sync_that_detaches_more_rows_than_the_total_of_entries(): void
    {
        config(['rest.limits.totals.entries' => 3]);
        DB::table('tag_ticket')->insert([['tag_id' => 103, 'ticket_id' => 101, 'position' => 3], ['tag_id' => 106, 'ticket_id' => 101, 'position' => 6]]);
        TracingPolicy::$trace = [];
        $message = 'The mutate.0.relations.tags.0.key field leaves out more than 3 attached rows, the most that a sync detaches.';

        $response = $this->postJson('/api/tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'attributes' => ['title' => 'Paper jam'],
            'relations' => ['tags' => [['operation' => 'sync', 'key' => [102]]]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['mutate.0.relations.tags.0.key' => [$message]]]);
        $this->assertContains('sql '.self::ATTACHED.' and tags.deleted_at is null limit 5', TracingPolicy::$trace);
        $this->assertSame('Printer jam', Ticket::query()->findOrFail(101)->getAttribute('title'));
        $this->assertSame([101, 103, 104, 105, 106], self::attached());
    }

    #[Test]
    public function it_syncs_a_relation_that_detaches_as_many_rows_as_the_total_of_entries(): void
    {
        config(['rest.limits.totals.entries' => 3]);
        DB::table('tag_ticket')->insert(['tag_id' => 103, 'ticket_id' => 101, 'position' => 3]);
        TracingPolicy::$trace = [];

        $this->assertQueries('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tags' => [['operation' => 'sync', 'key' => [102]]]]],
        ]], [
            'select * from tickets where tickets.id in (101)',
            'select * from tags where tags.id in (102) and tags.deleted_at is null',
            self::ATTACHED.' and tags.deleted_at is null limit 5',
            'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?, ?, ?)',
            'select * from tag_ticket where tag_ticket.ticket_id = ?',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
        ]);
        $this->assertSame([102, 104], self::attached());
    }

    #[Test]
    public function it_syncs_the_whole_relation_after_a_toggle_of_the_same_relation(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([
            ['operation' => 'toggle', 'key' => [102]],
            ['operation' => 'sync', 'key' => [102]],
        ]), [
            'select * from tickets where tickets.id in (101)',
            'select * from tags where tags.id in (102) and tags.deleted_at is null',
            self::ATTACHED.' and tags.id in (?) and tags.deleted_at is null',
            self::ATTACHED.' and tags.deleted_at is null limit 1002',
            'select tag_id from tag_ticket where tag_ticket.ticket_id = ?',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
            'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?, ?)',
            'select * from tag_ticket where tag_ticket.ticket_id = ?',
        ], [
            'policy viewAny',
            'policy update Ticket(Printer jam)',
            'policy view Tag(Network)',
            'policy attachTag Ticket(Printer jam) Tag(Network)',
            'policy detachTag Ticket(Printer jam) Tag(Hardware)',
            'policy detachTag Ticket(Printer jam) Tag(Billing)',
        ]);
        $this->assertSame([102, 104], self::attached());
    }

    #[Test]
    public function it_toggles_against_the_rows_that_an_earlier_sync_of_the_same_relation_left(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([
            ['operation' => 'sync', 'key' => [102]],
            ['operation' => 'toggle', 'key' => [101]],
        ]), [
            'select * from tickets where tickets.id in (101)',
            'select * from tags where tags.id in (102, 101) and tags.deleted_at is null',
            self::ATTACHED.' and tags.deleted_at is null limit 1002',
            'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?, ?)',
            'select * from tag_ticket where tag_ticket.ticket_id = ?',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
            'select tag_id from tag_ticket where tag_ticket.ticket_id = ?',
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)',
        ], [
            'policy viewAny',
            'policy update Ticket(Printer jam)',
            'policy view Tag(Network)',
            'policy attachTag Ticket(Printer jam) Tag(Network)',
            'policy detachTag Ticket(Printer jam) Tag(Hardware)',
            'policy detachTag Ticket(Printer jam) Tag(Billing)',
            'policy view Tag(Hardware)',
            'policy attachTag Ticket(Printer jam) Tag(Hardware)',
        ]);
        $this->assertSame([101, 102, 104], self::attached());
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private static function tags(array $entries): array
    {
        return ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['tags' => $entries]]]];
    }

    /** @return list<int> */
    private static function attached(): array
    {
        return DB::table('tag_ticket')
            ->where('ticket_id', 101)
            ->orderBy('tag_id')
            ->pluck('tag_id')
            ->map(fn (mixed $key): int => (int) $key)
            ->all();
    }
}
