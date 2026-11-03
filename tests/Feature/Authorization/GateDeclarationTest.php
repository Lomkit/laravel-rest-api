<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MisdeclaredGateTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MisdeclaredGateTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GateDeclarationTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('misdeclared-gate-tickets', MisdeclaredGateTicketController::class)->register();
    }

    #[Test]
    public function it_reads_the_declared_gates_once_per_request(): void
    {
        $resource = $this->contextFor(null)->resource(TicketResource::class);
        $definition = $resource->context()->definition($resource);

        $this->assertSame($definition->gates(), $definition->gates());
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('readers')]
    public function it_refuses_a_misdeclared_gate_when_the_gates_are_read(string $method, string $uri, array $payload): void
    {
        $this->withoutExceptionHandling();
        $this->expectExceptionObject(InvalidResourceException::invalidGate(MisdeclaredGateTicketResource::class.'::gates()', 1, 'view'));

        $this->json($method, $uri, $payload);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function readers(): array
    {
        return [
            'the details' => ['GET', '/misdeclared-gate-tickets', []],
            'a search with gates' => ['POST', '/misdeclared-gate-tickets/search', ['search' => ['gates' => ['update']]]],
        ];
    }

    #[Test]
    public function it_searches_without_reading_the_gates_when_the_search_lists_none(): void
    {
        $response = $this->postJson('/misdeclared-gate-tickets/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
