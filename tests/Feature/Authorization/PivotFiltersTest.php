<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotFiltersTest extends HelpdeskTestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function pivotColumns(): array
    {
        return [
            'belongs to many' => ['/api/tickets/search', 'tags.pivot.position', 'tags'],
            'morph to many' => ['/api/tickets/search', 'labels.pivot.position', 'tags'],
            'morphed by many' => ['/api/tags/search', 'labelledTickets.pivot.position', 'tickets'],
        ];
    }

    #[Test]
    public function it_filters_on_a_pivot_column_of_a_belongs_to_many_relation(): void
    {
        ['Printer jam' => $jam, 'Printer toner' => $toner] = $this->seedTickets();
        $hardware = Tag::create(['name' => 'Hardware']);
        $jam->tags()->attach($hardware, ['position' => 1]);
        $toner->tags()->attach($hardware, ['position' => 2]);

        $this->assertTickets([$jam], 'tags.pivot.position');
        $this->assertSame([['viewAny'], ['viewAny']], [$this->tickets->calls, $this->tags->calls]);
    }

    #[Test]
    public function it_filters_on_a_pivot_column_of_a_morph_to_many_relation(): void
    {
        ['Printer jam' => $jam, 'Printer toner' => $toner] = $this->seedTickets();
        $hardware = Tag::create(['name' => 'Hardware']);
        $jam->labels()->attach($hardware, ['position' => 1]);
        $toner->labels()->attach($hardware, ['position' => 2]);

        $this->assertTickets([$jam], 'labels.pivot.position');
        $this->assertSame([['viewAny'], ['viewAny']], [$this->tickets->calls, $this->tags->calls]);
    }

    #[Test]
    public function it_filters_on_a_pivot_column_of_a_morphed_by_many_relation(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $hardware = Tag::create(['name' => 'Hardware']);
        $network = Tag::create(['name' => 'Network']);
        $hardware->labelledTickets()->attach($jam, ['position' => 1]);
        $network->labelledTickets()->attach($jam, ['position' => 2]);

        $this->postJson('/api/tags/search', ['search' => ['filters' => [['field' => 'labelledTickets.pivot.position', 'value' => 1]]]])
            ->assertOk()
            ->assertExactJson($this->page([['id' => $hardware->id, 'name' => 'Hardware']]));

        $this->assertSame([['viewAny'], ['viewAny']], [$this->tags->calls, $this->tickets->calls]);
    }

    #[Test]
    #[DataProvider('pivotColumns')]
    public function it_refuses_a_pivot_column_of_a_resource_the_user_cannot_list(string $uri, string $field, string $policy): void
    {
        $this->{$policy}->answers['viewAny'] = Response::deny('This list is closed.');
        $this->logQueries();

        $this->postJson($uri, ['search' => ['filters' => [['field' => $field, 'value' => 1]]]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This list is closed.']);

        $this->assertNoQueries();
    }

    /** @param list<Ticket> $tickets */
    private function assertTickets(array $tickets, string $field): void
    {
        $this->postJson('/api/tickets/search', ['search' => ['filters' => [
            ['field' => 'status', 'value' => 'open'],
            ['field' => $field, 'value' => 1],
        ]]])->assertOk()->assertExactJson($this->page(array_map(fn (Ticket $ticket): array => [
            'id' => $ticket->id,
            'team_id' => $ticket->team_id,
            'title' => $ticket->title,
            'status' => $ticket->status,
        ], $tickets)));
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private function page(array $data): array
    {
        return [
            'current_page' => 1,
            'data' => $data,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($data),
            'total' => count($data),
            'meta' => [],
        ];
    }
}
