<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\Test;

class PivotQueryCountTest extends WriteTraceTestCase
{
    private const string TICKET = 'select * from tickets where tickets.id in (101)';

    private const string RENAME = 'update tickets set title = ?, updated_at = ? where id = ?';

    private const string DETACH = 'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?)';

    private const string ATTACH = 'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?)';

    private const string SYNC = 'select * from tag_ticket where tag_ticket.ticket_id = ?';

    private const string TOGGLE = 'select tag_id from tag_ticket where tag_ticket.ticket_id = ?';

    private const string ATTACHED = 'select tags.*, tag_ticket.ticket_id as pivot_ticket_id, tag_ticket.tag_id as pivot_tag_id, tag_ticket.position as pivot_position, tag_ticket.weight as pivot_weight, tag_ticket.added_by as pivot_added_by, tag_ticket.note as pivot_note, tag_ticket.created_at as pivot_created_at from tags inner join tag_ticket on tags.id = tag_ticket.tag_id where tag_ticket.ticket_id = ? and tags.deleted_at is null';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Tag::query()->insert([['id' => 103, 'name' => 'Urgent', 'deleted_at' => null], ['id' => 104, 'name' => 'Archived', 'deleted_at' => '2026-01-01 00:00:00']]);
        TracingPolicy::$trace = [];
    }

    #[Test]
    public function it_reads_the_rows_of_pivot_key_lists_once(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([
            ['operation' => 'detach', 'key' => [101, 102]],
            ['operation' => 'attach', 'key' => [101], 'pivot' => ['position' => 7]],
            ['operation' => 'sync', 'key' => [101, 103]],
            ['operation' => 'toggle', 'key' => [102, 103]],
        ]), [
            self::TICKET,
            'select * from tags where tags.id in (101, 102, 103) and tags.deleted_at is null',
            self::ATTACHED,
            self::RENAME,
            'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?, ?)',
            'insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?)',
            self::SYNC,
            self::ATTACH,
            self::TOGGLE,
            self::DETACH,
            self::ATTACH,
        ]);
    }

    #[Test]
    public function it_reads_the_rows_of_single_key_pivot_entries_once(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([
            ['operation' => 'detach', 'key' => [101]],
            ['operation' => 'detach', 'key' => [102]],
            ['operation' => 'attach', 'key' => [101], 'pivot' => ['position' => 7]],
            ['operation' => 'sync', 'key' => [101]],
            ['operation' => 'sync', 'key' => [103], 'without_detaching' => true],
            ['operation' => 'toggle', 'key' => [102]],
            ['operation' => 'toggle', 'key' => [103]],
        ]), [
            self::TICKET,
            'select * from tags where tags.id in (101, 102, 103) and tags.deleted_at is null',
            self::ATTACHED,
            self::RENAME,
            self::DETACH,
            self::DETACH,
            'insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?)',
            self::SYNC,
            self::SYNC,
            self::ATTACH,
            self::TOGGLE,
            self::ATTACH,
            self::TOGGLE,
            self::DETACH,
        ]);
    }

    #[Test]
    public function it_reads_a_tag_that_each_operation_names_once(): void
    {
        $this->assertQueries('/api/tickets/mutate', self::tags([
            ['operation' => 'attach', 'key' => [101, 102]],
            ['operation' => 'detach', 'key' => [101]],
            ['operation' => 'sync', 'key' => [101, 102]],
            ['operation' => 'toggle', 'key' => [101]],
        ]), [
            self::TICKET,
            'select * from tags where tags.id in (101, 102) and tags.deleted_at is null',
            self::ATTACHED,
            self::RENAME,
            'insert into tag_ticket (created_at, tag_id, ticket_id) values (?, ?, ?), (?, ?, ?)',
            self::DETACH,
            self::SYNC,
            self::ATTACH,
            self::TOGGLE,
            self::DETACH,
        ]);
    }

    #[Test]
    public function it_reads_the_rows_that_a_sync_detaches_without_naming_them_with_one_query(): void
    {
        DB::table('tag_ticket')->insert([['tag_id' => 103, 'ticket_id' => 101, 'position' => 2], ['tag_id' => 104, 'ticket_id' => 101, 'position' => 3]]);
        TracingPolicy::$trace = [];

        $this->assertQueries('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tags' => [['operation' => 'sync', 'key' => [102]]]]],
        ]], [
            self::TICKET,
            'select * from tags where tags.id in (102) and tags.deleted_at is null',
            self::ATTACHED,
            'delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?, ?)',
            self::SYNC,
            self::ATTACH,
        ]);
        $this->assertSame(
            ['policy update Ticket(Printer jam)', 'policy view Tag(Network)', 'policy attachTag Ticket(Printer jam) Tag(Network)', 'policy detachTag Ticket(Printer jam) Tag(Hardware)', 'policy detachTag Ticket(Printer jam) Tag(Urgent)'],
            array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy '))),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private static function tags(array $entries): array
    {
        return ['mutate' => [['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Paper jam'], 'relations' => ['tags' => $entries]]]];
    }
}
