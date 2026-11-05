<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use PHPUnit\Framework\Attributes\Test;

class NestedAggregateReachTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderDenials();
    }

    #[Test]
    public function it_checks_each_level_of_a_relation_path_before_any_query(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->logQueries();

        $response = $this->postJson('/api/tickets/search', ['search' => [
            'aggregates' => [['relation' => 'team.tickets', 'type' => 'count']],
        ]]);

        $response->assertForbidden()->assertExactJson([
            'message' => 'Teams are not listed.',
            'denials' => [['path' => 'search.aggregates.0', 'ability' => 'viewAny', 'message' => 'Teams are not listed.']],
        ]);
        $this->assertNoQueries();
        $this->assertSame(['viewAny'], $this->teams->calls);
    }

    #[Test]
    public function it_checks_the_last_level_of_a_relation_path_before_any_query(): void
    {
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');
        $this->logQueries();

        $response = $this->postJson('/api/teams/search', ['search' => [
            'aggregates' => [['relation' => 'tickets.tags', 'type' => 'count']],
        ]]);

        $response->assertForbidden()->assertExactJson([
            'message' => 'Tags are not listed.',
            'denials' => [['path' => 'search.aggregates.0', 'ability' => 'viewAny', 'message' => 'Tags are not listed.']],
        ]);
        $this->assertNoQueries();
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_each_level_of_a_relation_path_once(): void
    {
        $this->seedTickets();

        $response = $this->postJson('/api/tickets/search', ['search' => [
            'aggregates' => [['relation' => 'team.tickets', 'type' => 'count'], ['relation' => 'tags.labelledTickets', 'type' => 'count']],
        ]]);

        $response->assertOk();
        $this->assertSame(['viewAny'], $this->teams->calls);
        $this->assertSame(['viewAny'], $this->tags->calls);
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }
}
