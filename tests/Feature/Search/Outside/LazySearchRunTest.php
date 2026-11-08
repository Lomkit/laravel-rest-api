<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventTicketResource;
use Lomkit\Rest\Tests\Feature\Search\Outside\Stubs\DeskTeamResource;
use Lomkit\Rest\Tests\Feature\Search\Outside\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\Test;

class LazySearchRunTest extends HelpdeskTestCase
{
    private const array BY_ID = ['sorts' => [['field' => 'id']]];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('teams')->insert([['id' => 1, 'name' => 'Billing'], ['id' => 2, 'name' => 'Support']]);

        foreach (range(1, 7) as $id) {
            DB::table('tickets')->insert(['id' => $id, 'team_id' => $id % 2 + 1, 'title' => "Ticket {$id}", 'status' => $id <= 4 ? 'open' : 'closed']);
        }

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
        EventLog::reset();
    }

    #[Test]
    public function it_reads_the_keys_once_then_the_rows_chunk_by_chunk(): void
    {
        EventLog::listen();

        $rows = Rest::search(EventTicketResource::class)
            ->as(null)
            ->payload([...self::BY_ID, 'includes' => [['relation' => 'team']]])
            ->lazy(chunk: 3);

        $this->assertSame(['searching', 'sql select tickets'], EventLog::$lines);

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = [$row['id'], $row['team']['id']];
            EventLog::record("row {$row['id']}");
        }

        $this->assertSame([[1, 2], [2, 1], [3, 2], [4, 1], [5, 2], [6, 1], [7, 2]], $ids);
        $this->assertSame([
            'searching',
            'sql select tickets',
            'sql select tickets', 'sql select teams', 'row 1', 'row 2', 'row 3',
            'sql select tickets', 'sql select teams', 'row 4', 'row 5', 'row 6',
            'sql select tickets', 'sql select teams', 'row 7',
        ], EventLog::$lines);
    }

    #[Test]
    public function it_reads_the_rows_of_the_given_user_at_every_level(): void
    {
        $tickets = Rest::search(DeskTicketResource::class)
            ->as(new GenericUser(['id' => 2]))
            ->payload(self::BY_ID)
            ->lazy(chunk: 2);
        $teams = Rest::search(DeskTeamResource::class)
            ->as(new GenericUser(['id' => 2]))
            ->payload([...self::BY_ID, 'includes' => [['relation' => 'tickets', ...self::BY_ID]]])
            ->lazy(chunk: 1);

        $this->assertSame([1, 3, 5, 7], $tickets->pluck('id')->all());
        $this->assertSame([[], [1, 3, 5, 7]], $teams->map(fn (array $team): array => array_column($team['tickets'], 'id'))->all());
    }
}
