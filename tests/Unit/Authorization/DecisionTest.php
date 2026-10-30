<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Authorization\Decision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DecisionTest extends TestCase
{
    #[Test]
    public function it_keeps_a_denial_through_a_round_trip(): void
    {
        $response = Decision::fromArray(
            Decision::fromResponse(Response::denyWithStatus(423, 'Closed tickets are read-only.', 'ticket_closed'))->toArray(),
        )->toResponse();

        $this->assertSame(
            [false, 'Closed tickets are read-only.', 'ticket_closed', 423],
            [$response->allowed(), $response->message(), $response->code(), $response->status()],
        );
    }

    #[Test]
    public function it_keeps_an_approval_through_a_round_trip(): void
    {
        $response = Decision::fromArray(Decision::fromResponse(Response::allow('Welcome back.', 7))->toArray())->toResponse();

        $this->assertSame([true, 'Welcome back.', 7, null], [$response->allowed(), $response->message(), $response->code(), $response->status()]);
    }

    #[Test]
    public function it_holds_only_scalars(): void
    {
        $decision = Decision::fromResponse(Response::denyAsNotFound('No such ticket.', 404));

        $this->assertSame(['allowed' => false, 'message' => 'No such ticket.', 'code' => 404, 'status' => 404], $decision->toArray());
    }

    #[Test]
    public function it_allows_without_a_message(): void
    {
        $this->assertSame(['allowed' => true, 'message' => null, 'code' => null, 'status' => null], Decision::allow()->toArray());
    }

    #[Test]
    public function it_leaves_out_a_code_that_is_not_a_scalar(): void
    {
        $decision = Decision::fromResponse(Response::deny('Not today.', new \stdClass));

        $this->assertSame(['allowed' => false, 'message' => 'Not today.', 'code' => null, 'status' => null], $decision->toArray());
    }

    #[Test]
    public function it_reads_a_partial_array_as_a_denial_without_details(): void
    {
        $this->assertSame(
            ['allowed' => false, 'message' => null, 'code' => null, 'status' => null],
            Decision::fromArray(['allowed' => 'yes', 'status' => '423'])->toArray(),
        );
    }

    #[Test]
    public function it_throws_the_default_message_for_a_plain_denial(): void
    {
        $this->expectExceptionObject(new AuthorizationException('This action is unauthorized.'));

        Decision::fromResponse(Response::deny())->toResponse()->authorize();
    }
}
