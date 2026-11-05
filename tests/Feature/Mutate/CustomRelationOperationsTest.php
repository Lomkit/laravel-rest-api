<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Feature\Details\Stubs\TeamPivotTicketResource;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedLabelResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedTeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CustomRelationOperationsTest extends WriteTraceTestCase
{
    #[Test]
    #[DataProvider('linkOperations')]
    public function it_refuses_sync_and_toggle_on_a_belongs_to_relation_with_pivot_fields(string $operation): void
    {
        $this->app?->bind(TicketResource::class, TeamPivotTicketResource::class);
        Team::query()->insert(['id' => 102, 'name' => 'Ops']);

        $response = $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['team' => ['operation' => $operation, 'key' => 102]]],
        ]]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors', ['mutate.0.relations.team.operation' => ['The selected mutate.0.relations.team.operation is invalid.']]);
        $this->assertSame(101, self::teamOfPrinterJam());
    }

    #[Test]
    #[DataProvider('linkOperations')]
    public function it_checks_the_attach_of_each_row_that_a_custom_has_many_relation_links(string $operation): void
    {
        $this->app?->bind(TeamResource::class, SyncedTeamResource::class);
        Team::query()->insert(['id' => 102, 'name' => 'Ops']);
        TracingPolicy::$trace = [];

        $this->assertWrites('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => $operation, 'key' => [101]]]]],
        ]], [
            ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => $operation, 'key' => [101]]]]],
        ], [
            'sql select teams.id from teams where teams.id in (102)',
            'sql select tickets.id from tickets where tickets.id in (101)',
            'sql select * from teams where teams.id = ? limit 1',
            'policy update Team(Ops)',
            'saved Team(Ops)',
            'sql select * from tickets where tickets.id = ? limit 1',
            'policy view Ticket(Printer jam)',
            'sql update tickets set team_id = ?, updated_at = ? where id = ?',
            'saved Ticket(Printer jam)',
            'policy attachTicket Team(Ops) Ticket(Printer jam)',
        ]);
        $this->assertSame(102, self::teamOfPrinterJam());
    }

    #[Test]
    #[DataProvider('linkOperations')]
    public function it_checks_the_attach_of_the_row_that_a_custom_morph_to_relation_links(string $operation): void
    {
        $this->app?->bind(LabelResource::class, SyncedLabelResource::class);

        $this->assertWrites('/api/labels/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['labelable' => ['operation' => $operation, 'type' => Shelf::class, 'key' => 101]]],
        ]], [
            ['operation' => 'update', 'key' => 102, 'relations' => ['labelable' => ['operation' => $operation, 'type' => Shelf::class, 'key' => 101]]],
        ], [
            'sql select labels.id from labels where labels.id in (102)',
            'sql select shelves.id from shelves where shelves.id in (101)',
            'sql select * from labels where labels.id = ? limit 1',
            'policy update Label(loose)',
            'sql select * from shelves where shelves.id = ? limit 1',
            'policy view Shelf(Classics)',
            'saved Shelf(Classics)',
            'policy attachShelf Label(loose) Shelf(Classics)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(loose)',
        ]);
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) Shelf Shelf(Classics)'], self::rows('labels'));
    }

    /** @return array<string, array{string}> */
    public static function linkOperations(): array
    {
        return ['sync' => ['sync'], 'toggle' => ['toggle']];
    }

    private static function teamOfPrinterJam(): mixed
    {
        return Ticket::query()
            ->findOrFail(101)
            ->getAttribute('team_id');
    }
}
