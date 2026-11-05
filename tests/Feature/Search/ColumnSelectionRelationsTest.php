<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\TitledTeamController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Reply;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ColumnSelectionRelationsTest extends PackageTestCase
{
    private const array ID = [['field' => 'id']];

    private const string TEAMS = 'select teams.id from teams order by teams.id desc limit 50 offset 0';

    private const string TICKETS = 'select tickets.id, tickets.status from tickets order by tickets.id desc limit 50 offset 0';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('titled-teams', TitledTeamController::class)->register();
        Team::query()->create(['id' => 1, 'name' => 'Billing']);
        Ticket::query()->create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Reply::query()->create(['id' => 1, 'ticket_id' => 1, 'body' => 'On it']);
        Tag::query()->create(['id' => 1, 'name' => 'Urgent']);
        DB::table('tag_ticket')->insert(['tag_id' => 1, 'ticket_id' => 1, 'position' => 4]);
        DB::table('labelables')->insert(['tag_id' => 1, 'labelable_id' => 1, 'labelable_type' => Ticket::class, 'position' => 5]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $queries
     * @param  array<string, mixed>  $row
     */
    #[Test]
    #[DataProvider('levels')]
    public function it_loads_the_columns_that_each_level_renders(string $uri, array $search, array $queries, array $row): void
    {
        DB::enableQueryLog();

        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertOk()->assertJsonPath('data', [$row]);
        $this->assertSame($queries, array_map(static fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog()));
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>, array<string, mixed>}> */
    public static function levels(): array
    {

        return [
            'a has many' => ['/api/teams/search', ['selects' => self::ID, 'includes' => [['relation' => 'tickets', 'selects' => self::ID]]], [self::TEAMS, 'select * from (select tickets.id, tickets.status, tickets.team_id, row_number() over (partition by tickets.team_id order by tickets.id desc) as laravel_row from tickets where tickets.team_id in (1)) as laravel_table where laravel_row <= 10 order by laravel_row'], ['id' => 1, 'tickets' => [['id' => 1]]]],
            'a has many through' => ['/api/teams/search', ['selects' => self::ID, 'includes' => [['relation' => 'replies', 'selects' => self::ID]]], [self::TEAMS, 'select * from (select replies.id, tickets.team_id as laravel_through_key, row_number() over (partition by tickets.team_id order by replies.id desc) as laravel_row from replies inner join tickets on tickets.id = replies.ticket_id where tickets.team_id in (1)) as laravel_table where laravel_row <= 10 order by laravel_row'], ['id' => 1, 'replies' => [['id' => 1]]]],
            'a belongs to many' => ['/api/tickets/search', ['selects' => self::ID, 'includes' => [['relation' => 'tags', 'selects' => self::ID]]], [self::TICKETS, 'select * from (select tags.id, tag_ticket.ticket_id as pivot_ticket_id, tag_ticket.tag_id as pivot_tag_id, tag_ticket.position as pivot_position, tag_ticket.weight as pivot_weight, tag_ticket.added_by as pivot_added_by, tag_ticket.note as pivot_note, tag_ticket.created_at as pivot_created_at, row_number() over (partition by tag_ticket.ticket_id order by tags.id desc) as laravel_row from tags inner join tag_ticket on tags.id = tag_ticket.tag_id where tag_ticket.ticket_id in (1) and tags.deleted_at is null) as laravel_table where laravel_row <= 10 order by laravel_row'], ['id' => 1, 'tags' => [['id' => 1, 'pivot' => ['position' => 4]]]]],
            'a morph to many' => ['/api/tickets/search', ['selects' => self::ID, 'includes' => [['relation' => 'labels', 'selects' => self::ID]]], [self::TICKETS, 'select * from (select tags.id, labelables.labelable_id as pivot_labelable_id, labelables.tag_id as pivot_tag_id, labelables.labelable_type as pivot_labelable_type, labelables.position as pivot_position, labelables.weight as pivot_weight, labelables.added_by as pivot_added_by, labelables.note as pivot_note, labelables.created_at as pivot_created_at, row_number() over (partition by labelables.labelable_id order by tags.id desc) as laravel_row from tags inner join labelables on tags.id = labelables.tag_id where labelables.labelable_id in (1) and labelables.labelable_type = ? and tags.deleted_at is null) as laravel_table where laravel_row <= 10 order by laravel_row'], ['id' => 1, 'labels' => [['id' => 1, 'pivot' => ['position' => 5]]]]],
            'a has many in a dotted include' => ['/titled-teams/search', ['selects' => self::ID, 'includes' => [['relation' => 'tickets.tags', 'selects' => self::ID]]], [self::TEAMS, 'select * from (select tickets.id, tickets.title, tickets.status, tickets.team_id, row_number() over (partition by tickets.team_id order by tickets.id desc) as laravel_row from tickets where tickets.team_id in (1)) as laravel_table where laravel_row <= 10 order by laravel_row', 'select * from (select tags.id, tag_ticket.ticket_id as pivot_ticket_id, tag_ticket.tag_id as pivot_tag_id, tag_ticket.position as pivot_position, tag_ticket.weight as pivot_weight, tag_ticket.added_by as pivot_added_by, tag_ticket.note as pivot_note, tag_ticket.created_at as pivot_created_at, row_number() over (partition by tag_ticket.ticket_id order by tags.id desc) as laravel_row from tags inner join tag_ticket on tags.id = tag_ticket.tag_id where tag_ticket.ticket_id in (1) and tags.deleted_at is null) as laravel_table where laravel_row <= 10 order by laravel_row'], ['id' => 1, 'tickets' => [['id' => 1, 'title' => 'Printer jam', 'tags' => [['id' => 1, 'pivot' => ['position' => 4]]]]]]],
            'the column of a policy' => ['/api/tickets/search', ['selects' => self::ID, 'gates' => ['update']], [self::TICKETS], ['id' => 1, 'gates' => ['authorized_to_update' => ['allowed' => true, 'message' => null]]]],
        ];
    }
}
