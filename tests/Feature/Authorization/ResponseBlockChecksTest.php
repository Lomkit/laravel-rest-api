<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class ResponseBlockChecksTest extends HelpdeskTestCase
{
    private Ticket $jam;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->jam = $this->seedTickets()['Printer jam'];
    }

    #[Test]
    public function it_checks_the_listing_of_the_records_before_writing(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->assertRefusedBeforeWriting([], 'Tickets are not listed.');

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_the_listing_of_an_included_relation_before_writing(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');

        $this->assertRefusedBeforeWriting(['includes' => [['relation' => 'team']]], 'Teams are not listed.');

        $this->assertSame(['viewAny'], $this->tickets->calls);
        $this->assertSame(['viewAny'], $this->teams->calls);
    }

    #[Test]
    public function it_checks_the_listing_of_an_aggregated_relation_before_writing(): void
    {
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');

        $this->assertRefusedBeforeWriting(['aggregates' => [['relation' => 'tags', 'type' => 'count']]], 'Tags are not listed.');

        $this->assertSame(['viewAny'], $this->tags->calls);
    }

    #[Test]
    public function it_answers_the_gates_of_a_record_as_the_mutation_left_it(): void
    {
        $this->postJson('/api/tickets/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $this->jam->id, 'attributes' => ['status' => 'closed']]],
            'response' => ['selects' => [['field' => 'status']], 'gates' => ['update', 'escalate']],
        ])
            ->assertOk()
            ->assertExactJson([
                'operations' => [['operation' => 'update', 'key' => $this->jam->id]],
                'records' => [[
                    'status' => 'closed',
                    'gates' => [
                        'authorized_to_update' => ['allowed' => false, 'message' => 'Closed tickets are read-only.'],
                        'authorized_to_escalate' => ['allowed' => false, 'message' => 'Closed tickets are not escalated.'],
                    ],
                ]],
            ]);

        $this->assertSame(['viewAny', ...$this->calls('update', $this->jam, $this->jam), ...$this->calls('escalate', $this->jam)], $this->tickets->calls);
    }

    #[Test]
    public function it_returns_the_record_of_a_row_whose_key_is_cast_to_an_enum(): void
    {
        Channel::create(['code' => 'phone', 'name' => 'Phone']);

        $this->postJson('/api/channels/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 'phone', 'attributes' => ['name' => 'Hotline']]],
            'response' => ['gates' => ['view']],
        ])
            ->assertOk()
            ->assertJsonPath('records', [['code' => 'phone', 'name' => 'Hotline', 'gates' => ['authorized_to_view' => ['allowed' => true, 'message' => null]]]]);

        $this->assertSame(['viewAny', 'update phone', 'view phone'], $this->channels->calls);
    }

    /** @param array<string, mixed> $block */
    private function assertRefusedBeforeWriting(array $block, string $message): void
    {
        $this->logQueries();

        $this->postJson('/api/tickets/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $this->jam->id, 'attributes' => ['title' => 'Paper jam']]],
            'response' => $block,
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => $message]);

        $this->assertNoQueries();
        $this->assertSame('Printer jam', $this->jam->refresh()->title);
    }
}
