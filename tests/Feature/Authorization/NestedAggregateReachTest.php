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
    public function it_checks_each_level_of_a_nested_aggregate_before_refusing_it(): void
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
}
