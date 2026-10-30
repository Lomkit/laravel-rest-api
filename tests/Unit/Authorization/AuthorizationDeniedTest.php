<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\Denial;
use Lomkit\Rest\Exceptions\AuthorizationDenied;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuthorizationDeniedTest extends TestCase
{
    #[Test]
    public function it_takes_the_message_the_code_and_the_status_of_the_first_denial(): void
    {
        $exception = new AuthorizationDenied([
            Denial::of(Check::onClass(new TeamResource, 'viewAny', 'search.filters.0'), new Decision(false, 'Teams are not listed.', 'teams_private', 423)),
            Denial::of(Check::onClass(new TicketResource, 'viewAny', 'search.includes.0'), new Decision(false, 'Tickets are not listed.')),
        ]);

        $this->assertInstanceOf(AuthorizationException::class, $exception);
        $this->assertSame(
            ['Teams are not listed.', 'teams_private', 423, 'Teams are not listed.'],
            [$exception->getMessage(), $exception->getCode(), $exception->status(), $exception->response()?->message()],
        );
        $this->assertSame([
            ['path' => 'search.filters.0', 'ability' => 'viewAny', 'message' => 'Teams are not listed.'],
            ['path' => 'search.includes.0', 'ability' => 'viewAny', 'message' => 'Tickets are not listed.'],
        ], $exception->toArray());
    }

    #[Test]
    public function it_keeps_the_default_message_and_no_status_of_a_plain_denial(): void
    {
        $exception = new AuthorizationDenied([Denial::of(Check::onClass(new TeamResource, 'viewAny'), new Decision(false))]);

        $this->assertSame(['This action is unauthorized.', false], [$exception->getMessage(), $exception->hasStatus()]);
    }

    #[Test]
    public function it_keeps_the_first_fifty_denials(): void
    {
        $denials = array_map(
            fn (int $index): Denial => Denial::of(Check::onClass(new TeamResource, 'viewAny', 'search.filters.'.$index), new Decision(false)),
            range(0, 59),
        );

        $exception = new AuthorizationDenied($denials);

        $this->assertCount(50, $exception->denials);
        $this->assertSame('search.filters.49', $exception->denials[49]->path);
    }
}
