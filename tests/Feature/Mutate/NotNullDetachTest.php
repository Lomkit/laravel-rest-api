<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use PHPUnit\Framework\Attributes\Test;

class NotNullDetachTest extends HelpdeskTestCase
{
    use AssertsExactErrors;

    private const string KEPT = 'field is prohibited: this relation cannot be detached, its foreign key does not accept null.';

    #[Test]
    public function it_refuses_to_detach_a_child_whose_foreign_key_does_not_accept_null(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $tickets = self::tickets();

        $response = $this->postJson('/api/teams/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $jam->team_id,
            'relations' => ['tickets' => [['operation' => 'detach', 'key' => [$jam->id]]]],
        ]]]);

        $this->assertExactErrors($response, ['mutate.0.relations.tickets.0.key' => ['The mutate.0.relations.tickets.0.key '.self::KEPT]]);
        $this->assertSame($tickets, self::tickets());
    }

    #[Test]
    public function it_refuses_to_detach_an_owner_whose_foreign_key_does_not_accept_null(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $tickets = self::tickets();

        $response = $this->postJson('/api/tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $jam->id,
            'relations' => ['team' => ['operation' => 'detach', 'key' => $jam->team_id]],
        ]]]);

        $this->assertExactErrors($response, ['mutate.0.relations.team.key' => ['The mutate.0.relations.team.key '.self::KEPT]]);
        $this->assertSame($tickets, self::tickets());
    }

    #[Test]
    public function it_reads_the_columns_of_a_table_once_per_request(): void
    {
        $rows = $this->seedTickets();
        $detach = fn (string $title): array => ['operation' => 'detach', 'key' => [$rows[$title]->id]];
        $team = $rows['Printer jam']->team_id;

        $once = $this->queries(['tickets' => [$detach('Printer jam')]], $team);
        $twice = $this->queries(['tickets' => [$detach('Printer jam'), $detach('Printer toner')]], $team);

        $this->assertSame($once, $twice);
    }

    /** @param array<string, mixed> $relations */
    private function queries(array $relations, int $team): int
    {
        $this->logQueries();

        $this->postJson('/api/teams/mutate', ['mutate' => [['operation' => 'update', 'key' => $team, 'relations' => $relations]]])->assertUnprocessable();

        return count(DB::getQueryLog());
    }

    /** @return list<string> */
    private static function tickets(): array
    {
        return DB::table('tickets')
            ->orderBy('title')
            ->get(['title', 'team_id'])
            ->map(fn (object $ticket): string => $ticket->title.' > '.$ticket->team_id)
            ->all();
    }
}
