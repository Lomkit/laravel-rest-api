<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MiscontextTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MiscontextTicketResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TeamTicketController;
use PHPUnit\Framework\Attributes\Test;

class CacheContextTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('team-tickets', TeamTicketController::class)->register();
        Rest::resource('miscontext-tickets', MiscontextTicketController::class)->register();
    }

    #[Test]
    public function it_keeps_the_decisions_of_two_contexts_of_one_user_apart(): void
    {
        foreach ([1, 2, 1, 2] as $team) {
            $this->actingAs(new GenericUser(['id' => 1, 'current_team_id' => $team]))
                ->postJson('/team-tickets/search')
                ->assertOk();
        }

        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_names_a_context_value_that_is_not_scalar(): void
    {
        $this->withoutExceptionHandling();
        $this->expectExceptionObject(InvalidResourceException::invalidCacheContext(MiscontextTicketResource::class, 'team'));

        $this->postJson('/miscontext-tickets/search');
    }
}
