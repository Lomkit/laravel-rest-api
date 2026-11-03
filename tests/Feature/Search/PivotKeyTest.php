<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\TaggedTicket;
use Lomkit\Rest\Tests\Feature\Search\Stubs\TaggedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotKeyTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::create(['id' => 1, 'name' => 'Billing']);
        Tag::create(['id' => 1, 'name' => 'urgent']);
        Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Refund', 'status' => 'open'])
            ->labels()
            ->attach(1, ['position' => 2]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function pivotKeys(): array
    {
        return [
            'an aggregate alias' => [['relation' => 'labels', 'aggregates' => [['relation' => 'labelledTickets', 'type' => 'count', 'alias' => 'pivot']]], 'search.includes.0.aggregates.0.alias'],
            'an include alias' => [['relation' => 'labels', 'includes' => [['relation' => 'labelledTickets', 'alias' => 'pivot']]], 'search.includes.0.includes.0.alias'],
        ];
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('pivotKeys')]
    public function it_reserves_the_pivot_key_of_a_many_to_many_level(array $include, string $key): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => ['includes' => [$include]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The {$key} is reserved."]]);
    }

    #[Test]
    public function it_renders_the_pivot_of_a_many_to_many_level(): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => ['includes' => [['relation' => 'labels']]]]);

        $response->assertOk()->assertJsonPath('data.0.labels', [['id' => 1, 'name' => 'urgent', 'pivot' => ['position' => 2]]]);
    }

    #[Test]
    public function it_reserves_the_pivot_key_that_the_row_writes(): void
    {
        Rest::resource('tagged-tickets', TaggedTicketController::class)->register();

        $response = $this->postJson('/tagged-tickets/search', ['search' => ['includes' => [
            ['relation' => 'tags', 'aggregates' => [['relation' => 'labelledTickets', 'type' => 'count', 'alias' => 'ticket_tag']]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.includes.0.aggregates.0.alias' => ['The search.includes.0.aggregates.0.alias is reserved.']]);
    }

    #[Test]
    public function it_leaves_the_accessor_name_of_a_pivot_free(): void
    {
        Rest::resource('tagged-tickets', TaggedTicketController::class)->register();
        TaggedTicket::findOrFail(1)
            ->tags()
            ->attach(1, ['position' => 3]);

        $response = $this->postJson('/tagged-tickets/search', ['search' => ['includes' => [
            ['relation' => 'tags', 'aggregates' => [['relation' => 'labelledTickets', 'type' => 'count', 'alias' => 'ticketTag']]],
        ]]]);

        $response->assertOk()->assertJsonPath('data.0.tags', [['id' => 1, 'name' => 'urgent', 'ticketTag' => 1, 'ticket_tag' => ['position' => 3]]]);
    }
}
