<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Serialization\GatePrefetch;
use Lomkit\Rest\Http\Serialization\GateReach;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PreparingExtension;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;

class GatePrefetchTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Applicability::flush();
        PreparingExtension::$resources = null;
        PreparingExtension::$received = [];
        PreparingExtension::$prepare = null;
        config(['rest.authorizations.cache.enabled' => true]);
    }

    #[Test]
    public function it_batches_no_row_gate_that_is_neither_cached_nor_prepared(): void
    {
        $this->assertSame([], $this->rowChecks(TicketResource::class));
    }

    #[Test]
    public function it_batches_every_row_gate_of_a_resource_that_prepares(): void
    {
        $this->assertSame(['update 1', 'update 2', 'escalate 1', 'escalate 2'], $this->rowChecks(PreparedTicketResource::class));
    }

    #[Test]
    public function it_batches_every_row_gate_when_a_row_check_extension_applies(): void
    {
        config(['rest.extensions' => ['prep' => PreparingExtension::class]]);

        $this->assertSame(['update 1', 'update 2', 'escalate 1', 'escalate 2'], $this->rowChecks(TicketResource::class));
    }

    #[Test]
    public function it_batches_only_the_cached_row_gates_otherwise(): void
    {
        $this->assertSame(['update 1', 'update 2'], $this->rowChecks(CachedTicketResource::class));
    }

    #[Test]
    public function it_batches_no_row_gate_of_a_cached_resource_when_the_cache_is_off(): void
    {
        config(['rest.authorizations.cache.enabled' => false]);

        $this->assertSame([], $this->rowChecks(CachedTicketResource::class));
    }

    #[Test]
    public function it_batches_a_class_gate_of_a_resource_that_prepares_only_when_it_is_cached(): void
    {
        $plan = $this->plan(PreparedTicketResource::class, ['create']);

        $this->assertSame(['update 1', 'update 2', 'escalate 1', 'escalate 2'], self::described(GatePrefetch::page($plan, $this->rows(), GateReach::everyRow())));
    }

    /**
     * @param  class-string<Resource>  $resource
     * @return list<string>
     */
    private function rowChecks(string $resource): array
    {
        return self::described(GatePrefetch::rows($this->plan($resource), $this->rows(), GateReach::everyRow()));
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  list<string>  $classGates
     */
    private function plan(string $resource, array $classGates = []): QueryPlan
    {
        $gates = array_map(static fn (string $name): GateAbility => new GateAbility($name), [...$classGates, 'update', 'escalate']);

        return new QueryPlan($this->contextFor(null)->resource($resource), [], [], [], LimitPolicy::none(), gates: $gates);
    }

    /** @return list<Ticket> */
    private function rows(): array
    {
        return [
            (new Ticket)->newFromBuilder(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']),
            (new Ticket)->newFromBuilder(['id' => 2, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'open']),
        ];
    }

    /**
     * @param  list<Check>  $checks
     * @return list<string>
     */
    private static function described(array $checks): array
    {
        return array_map(static fn (Check $check): string => $check->ability.' '.(is_string($check->subject) ? 'class' : $check->subject->getKey()), $checks);
    }
}
