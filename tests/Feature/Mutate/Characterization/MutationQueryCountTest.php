<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use PHPUnit\Framework\Attributes\Test;

class MutationQueryCountTest extends WriteTraceTestCase
{
    private const string UPDATE_BOOK = 'update books set title = ?, updated_at = ? where id = ?';

    private const string UPDATE_TICKET = 'update tickets set team_id = ?, updated_at = ? where id = ?';

    private const array MOVED_TICKETS = ['policy viewAny', 'policy update Team(Ops)', 'policy update Ticket(Printer jam)', 'policy update Ticket(Password reset)'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Book::query()->insert([['id' => 103, 'title' => 'Ubik'], ['id' => 104, 'title' => 'Solaris'], ['id' => 105, 'title' => 'Kindred']]);
        Team::query()->insert(['id' => 102, 'name' => 'Ops']);
        TracingPolicy::$trace = [];
    }

    #[Test]
    public function it_reads_the_rows_of_root_updates_with_one_query(): void
    {
        $this->assertQueries('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Dune Messiah']],
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Ubik (1969)']],
            ['operation' => 'update', 'key' => 104, 'attributes' => ['title' => 'Solaris (1961)']],
            ['operation' => 'update', 'key' => 105, 'attributes' => ['title' => 'Kindred (1979)']],
        ]], [
            'select * from books where books.id in (101, 102, 103, 104, 105)',
            self::UPDATE_BOOK,
            self::UPDATE_BOOK,
            self::UPDATE_BOOK,
            self::UPDATE_BOOK,
            self::UPDATE_BOOK,
        ], [
            'policy viewAny',
            'policy update Book(Dune)',
            'policy update Book(Emma)',
            'policy update Book(Ubik)',
            'policy update Book(Solaris)',
            'policy update Book(Kindred)',
        ]);
    }

    #[Test]
    public function it_reads_the_rows_of_a_key_list_with_one_query(): void
    {
        $this->assertQueries('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => [101, 102]]]]],
        ]], [
            'select * from teams where teams.id in (102)',
            'select * from tickets where tickets.id in (101, 102)',
            self::UPDATE_TICKET,
            self::UPDATE_TICKET,
        ], self::MOVED_TICKETS);
    }

    #[Test]
    public function it_reads_the_rows_of_single_key_entries_with_one_query(): void
    {
        $this->assertQueries('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [
                ['operation' => 'attach', 'key' => 101],
                ['operation' => 'attach', 'key' => 102],
            ]]],
        ]], [
            'select * from teams where teams.id in (102)',
            'select * from tickets where tickets.id in (101, 102)',
            self::UPDATE_TICKET,
            self::UPDATE_TICKET,
        ], self::MOVED_TICKETS);
    }

    #[Test]
    public function it_reads_an_owner_that_two_entries_attach_once(): void
    {
        $this->assertQueries('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['team' => ['operation' => 'attach', 'key' => 102]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['team' => ['operation' => 'attach', 'key' => 102]]],
        ]], [
            'select * from tickets where tickets.id in (101, 102)',
            'select * from teams where teams.id in (102)',
            self::UPDATE_TICKET,
            self::UPDATE_TICKET,
        ], ['policy viewAny', 'policy update Ticket(Printer jam)', 'policy view Team(Ops)', 'policy update Ticket(Password reset)']);
    }

    #[Test]
    public function it_reads_the_owner_and_the_pivot_rows_of_a_create_once(): void
    {
        $this->assertQueries('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Fan noise', 'status' => 'open'], 'relations' => [
                'team' => ['operation' => 'attach', 'key' => 101],
                'tags' => [['operation' => 'attach', 'key' => [101, 102], 'pivot' => ['position' => 3]]],
            ]],
        ]], [
            'select * from teams where teams.id in (101)',
            'select * from tags where tags.id in (101, 102) and tags.deleted_at is null',
            'insert into tickets (team_id, title, status, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?), (?, ?, ?, ?)',
        ], [
            'policy viewAny',
            'policy create',
            'policy create',
            'policy view Team(Support)',
            'policy view Tag(Hardware)',
            'policy view Tag(Network)',
            'policy attachTag Ticket(Fan noise) Tag(Hardware)',
            'policy attachTag Ticket(Fan noise) Tag(Network)',
        ]);
    }

    #[Test]
    public function it_reads_no_row_for_the_children_that_a_create_writes(): void
    {
        $this->assertQueries('/api/books/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Ubik'], 'relations' => [
                'labels' => [['operation' => 'create', 'attributes' => ['text' => 'new']], ['operation' => 'create', 'attributes' => ['text' => 'signed']]],
                'shelves' => [['operation' => 'attach', 'key' => [101]], ['operation' => 'attach', 'key' => [102]]],
            ]],
        ]], [
            'select * from shelves where shelves.id in (101, 102)',
            'insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'insert into labels (text, labelable_id, labelable_type, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'insert into labels (text, labelable_id, labelable_type, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'insert into book_shelf (book_id, shelf_id) values (?, ?)',
        ], [
            'policy viewAny',
            'policy create',
            'policy create',
            'policy create',
            'policy create',
            'policy create',
            'policy view Shelf(Classics)',
            'policy view Shelf(Poetry)',
        ]);
    }

    #[Test]
    public function it_reads_the_written_rows_once_more_for_the_response_block(): void
    {
        $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ['operation' => 'create', 'attributes' => ['title' => 'Fahrenheit 451']],
        ], 'response' => []])->assertOk();
        $queries = array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'sql ')));

        $created = Book::query()
            ->where('title', 'Fahrenheit 451')
            ->sole()
            ->getKey();
        $this->assertSame([
            'sql select * from books where books.id in (102)',
            'sql '.self::UPDATE_BOOK,
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            "sql select books.id, books.title from books where books.id in (102, {$created})",
        ], $queries);
    }
}
