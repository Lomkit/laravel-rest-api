<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class NestedEntryTotalsTest extends HelpdeskTestCase
{
    private Ticket $jam;

    /** @var list<int> */
    private array $ticketKeys = [];

    /** @var list<int> */
    private array $tagKeys = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::create(['name' => 'Billing']);
        $this->jam = Ticket::create(['team_id' => $team->id, 'title' => 'Printer jam', 'status' => 'open']);

        foreach (['Hardware', 'Network', 'Monitor', 'Printer', 'Laptop', 'Phone', 'Badge', 'Desk', 'Chair', 'Screen'] as $name) {
            $this->ticketKeys[] = Ticket::create(['team_id' => $team->id, 'title' => $name.' issue', 'status' => 'open'])->id;
            $this->tagKeys[] = Tag::create(['name' => $name])->id;
        }
    }

    #[Test]
    public function it_counts_a_nested_key_list_once_for_each_row_of_its_parent(): void
    {
        config(['rest.limits.totals.entries' => 16]);

        $response = $this->mutateTags([[
            'operation' => 'attach',
            'key' => array_slice($this->tagKeys, 0, 3),
            'relations' => ['labelledTickets' => [['operation' => 'attach', 'key' => array_slice($this->ticketKeys, 0, 3)]]],
        ]]);

        $this->assertRefused($response, 'mutate.0.relations.tags.0.relations.labelledTickets.0.key', 'The mutate.0.relations.tags.0.relations.labelledTickets.0.key field goes over the limit of 16 list entries in one request.');
        $this->assertSame(0, DB::table('tag_ticket')->count());
        $this->assertSame(0, DB::table('labelables')->count());
    }

    #[Test]
    public function it_admits_nested_entries_up_to_the_total(): void
    {
        config(['rest.limits.totals.entries' => 17]);

        $this->mutateTags([[
            'operation' => 'attach',
            'key' => array_slice($this->tagKeys, 0, 3),
            'relations' => ['labelledTickets' => [['operation' => 'attach', 'key' => array_slice($this->ticketKeys, 0, 3)]]],
        ]])->assertOk();

        $this->assertSame(3, DB::table('tag_ticket')->count());
        $this->assertSame(9, DB::table('labelables')->count());
    }

    #[Test]
    public function it_bounds_nested_key_lists_with_the_default_total(): void
    {
        $response = $this->mutateTags([[
            'operation' => 'attach',
            'key' => $this->tagKeys,
            'relations' => ['labelledTickets' => [[
                'operation' => 'attach',
                'key' => $this->ticketKeys,
                'relations' => ['tags' => [['operation' => 'attach', 'key' => $this->tagKeys]]],
            ]]],
        ]]);

        $this->assertRefused($response, 'mutate.0.relations.tags.0.relations.labelledTickets.0.relations.tags.0.key', 'The mutate.0.relations.tags.0.relations.labelledTickets.0.relations.tags.0.key field goes over the limit of 1000 list entries in one request.');
        $this->assertSame(0, DB::table('tag_ticket')->count());
        $this->assertSame(0, DB::table('labelables')->count());
    }

    /** @param list<array<string, mixed>> $operations */
    private function mutateTags(array $operations): TestResponse
    {
        return $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->jam->id, 'relations' => ['tags' => $operations]],
        ]]);
    }

    private function assertRefused(TestResponse $response, string $key, string $message): void
    {
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }
}
