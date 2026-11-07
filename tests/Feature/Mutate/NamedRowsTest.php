<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\TrashedTagController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class NamedRowsTest extends WriteTraceTestCase
{
    #[Test]
    public function it_checks_each_entry_that_names_a_row_against_the_changes_of_the_entries_before_it(): void
    {
        $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma W.']],
        ]])->assertOk();

        $this->assertSame(
            ['policy viewAny', 'policy update Book(Emma)', 'policy update Book(Emma Woodhouse)', 'policy update Book(Emma Woodhouse)', 'saved Book(Emma Woodhouse)', 'saved Book(Emma Woodhouse)', 'saved Book(Emma W.)'],
            array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => ! str_starts_with($line, 'sql '))),
        );
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma W.) []'], self::rows('books'));
    }

    #[Test]
    public function it_writes_a_row_that_two_resources_name_as_one_row(): void
    {
        $this->postJson('/api/uncached-tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Paper jam']],
            ['operation' => 'update', 'key' => 102, 'relations' => ['team' => [
                'operation' => 'update',
                'key' => 101,
                'relations' => ['tickets' => [['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Printer jam']]]],
            ]]],
        ]])->assertOk();

        $this->assertSame(['Ticket(Password reset) open [] []', 'Ticket(Printer jam) open [Tag(Hardware) 1] []'], self::rows('tickets'));
    }

    #[Test]
    public function it_refuses_the_key_of_a_row_that_the_same_request_creates(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Solaris']],
            ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Solaris (1961)']],
        ]]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors', ['mutate.1.key' => ['The selected mutate.1.key is invalid.']]);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma) []'], self::rows('books'));
    }

    #[Test]
    public function it_writes_the_row_that_an_entry_names_again_under_its_own_relations_in_entry_order(): void
    {
        $this->postJson('/api/loans/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada'], 'relations' => ['book' => [
                'operation' => 'update',
                'key' => 101,
                'relations' => ['loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']]],
            ]]],
        ]])->assertOk();

        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) -'], self::rows('loans'));
    }

    #[Test]
    public function it_writes_nothing_on_a_row_deleted_after_its_key_was_checked(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains(str_replace(['"', '`'], '', $query->sql), 'from books where books.id in (')) {
                DB::table('books')
                    ->where('id', 102)
                    ->delete();
            }
        });

        $response = $this->postJson('/api/books/mutate', ['mutate' => [['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 102]]]);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]'], self::rows('books'));
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.values', 1001)]
    #[WithConfig('rest.limits.totals.entries', 1003)]
    public function it_reads_the_keys_of_one_resource_in_batches_of_1000(): void
    {
        Team::query()->insert(['id' => 102, 'name' => 'Ops']);

        foreach (array_chunk(range(1001, 2001), 250) as $keys) {
            Ticket::query()->insert(array_map(fn (int $key): array => ['id' => $key, 'team_id' => 102, 'title' => 'Ticket '.$key, 'status' => 'open'], $keys));
        }

        TracingPolicy::$trace = [];

        $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => range(1001, 2001)]]]],
        ]])->assertOk();

        $reads = array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'sql select')));
        $this->assertSame(['sql select * from teams where teams.id in (101)', 'sql select * from tickets where tickets.id in ('.implode(', ', range(1001, 2000)).')', 'sql select * from tickets where tickets.id in (2001)'], $reads);
        $this->assertSame(1001, Ticket::query()
            ->where('team_id', 101)
            ->where('id', '>', 1000)
            ->count());
    }

    #[Test]
    public function it_writes_a_trashed_row_that_the_perimeter_of_its_resource_takes_in(): void
    {
        Rest::resource('trashed-tags', TrashedTagController::class)->register();
        Tag::query()->insert(['id' => 103, 'name' => 'Archived', 'deleted_at' => '2026-01-01 00:00:00']);

        $response = $this->postJson('/trashed-tags/mutate', ['mutate' => [['operation' => 'update', 'key' => 103, 'attributes' => ['name' => 'Restored name']]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 103]]]);
        $this->assertSame('Restored name', Tag::withTrashed()
            ->findOrFail(103)
            ->getAttribute('name'));
    }
}
