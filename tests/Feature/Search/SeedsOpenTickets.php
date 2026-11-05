<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\OpenTeamController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\OpenTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

trait SeedsOpenTickets
{
    private function seedOpenTickets(): void
    {
        Rest::resource('open-teams', OpenTeamController::class)->register();
        Rest::resource('open-tickets', OpenTicketController::class)->register();
        DB::table('teams')->insert([['id' => 1, 'name' => 'Core'], ['id' => 2, 'name' => 'Ops'], ['id' => 3, 'name' => 'Web']]);

        foreach ([[1, 1, 'Printer jam', 'open'], [2, 1, 'Paper jam', 'open'], [3, 1, 'Old jam', 'closed'], [4, 2, 'Fan noise', 'open'], [5, 3, 'Login loop', 'open']] as [$id, $team, $title, $status]) {
            DB::table('tickets')->insert(['id' => $id, 'team_id' => $team, 'title' => $title, 'status' => $status]);
        }

        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'Hardware', 'deleted_at' => null],
            ['id' => 2, 'name' => 'Network', 'deleted_at' => null],
            ['id' => 3, 'name' => 'Secret', 'deleted_at' => null],
            ['id' => 4, 'name' => 'Retired', 'deleted_at' => '2026-01-01 00:00:00'],
        ]);

        foreach ([[1, 1], [1, 2], [2, 3], [3, 1], [4, 2], [2, 4]] as [$tag, $ticket]) {
            DB::table('tag_ticket')->insert(['tag_id' => $tag, 'ticket_id' => $ticket]);
        }

        foreach ([[1, 1], [2, 1], [1, 4], [2, 4], [2, 5], [1, 3], [3, 2]] as [$tag, $ticket]) {
            DB::table('labelables')->insert(['tag_id' => $tag, 'labelable_type' => Ticket::class, 'labelable_id' => $ticket]);
        }
    }

    /** @param list<array<string, mixed>> $aggregates */
    private function search(string $uri, array $aggregates): TestResponse
    {
        return $this->postJson($uri, ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => $aggregates,
            'sorts' => [['field' => 'id']],
        ]]);
    }
}
