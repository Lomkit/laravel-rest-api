<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DisabledAuthorizationWritesTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::query()->insert(['id' => 101, 'name' => 'Support']);
        Ticket::query()->insert(['id' => 101, 'team_id' => 101, 'title' => 'Password reset', 'status' => 'closed']);
        $this->tickets->open = false;

        foreach (['viewAny', 'view', 'create', 'delete', 'deleteAny'] as $ability) {
            $this->tickets->answers[$ability] = Response::deny('Tickets are locked.');
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('writes')]
    public function it_writes_a_row_without_a_policy_call(array $entry, array $titles): void
    {
        $response = $this->postJson('/api/public-tickets/mutate', ['mutate' => [$entry]]);

        $response->assertOk();
        $this->assertSame($titles, Ticket::query()
            ->orderBy('title')
            ->pluck('title')
            ->all());
        $this->assertSame([], $this->tickets->calls);
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function writes(): array
    {
        return [
            'create' => [['operation' => 'create', 'attributes' => ['team_id' => 101, 'title' => 'Printer jam', 'status' => 'open']], ['Password reset', 'Printer jam']],
            'update' => [['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Password expired']], ['Password expired']],
        ];
    }

    #[Test]
    public function it_destroys_a_row_without_a_policy_call(): void
    {
        $response = $this->deleteJson('/api/public-tickets', ['resources' => [101]]);

        $response->assertOk()->assertExactJson(['data' => [['id' => 101, 'status' => 'closed', 'team_id' => 101, 'title' => 'Password reset']], 'meta' => []]);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame([], $this->tickets->calls);
    }
}
