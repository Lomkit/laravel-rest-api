<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GatePrefetchTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PreparingExtension::$resources = null;
        PreparingExtension::$received = [];
        PreparingExtension::$prepare = null;
        config(['rest.authorizations.cache.enabled' => true]);
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  list<class-string<Extension>>  $extensions
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('rowGates')]
    public function it_batches_the_row_gates_that_are_cached_or_prepared(string $resource, array $extensions, bool $cache, array $expected): void
    {
        config(['rest.extensions' => $extensions, 'rest.authorizations.cache.enabled' => $cache]);

        $this->assertSame($expected, $this->rowChecks($resource));
    }

    /** @return iterable<string, array{class-string<Resource>, list<class-string<Extension>>, bool, list<string>}> */
    public static function rowGates(): iterable
    {
        yield 'no row gate that is neither cached nor prepared' => [TicketResource::class, [], true, []];
        yield 'every row gate of a resource that prepares' => [PreparedTicketResource::class, [], true, ['update 1', 'update 2', 'escalate 1', 'escalate 2']];
        yield 'every row gate when a row check extension applies' => [TicketResource::class, [PreparingExtension::class], true, ['update 1', 'update 2', 'escalate 1', 'escalate 2']];
        yield 'only the cached row gates otherwise' => [CachedTicketResource::class, [], true, ['update 1', 'update 2']];
        yield 'no row gate of a cached resource when the cache is off' => [CachedTicketResource::class, [], false, []];
    }

    #[Test]
    public function it_batches_a_class_gate_of_a_resource_that_prepares_only_when_it_is_cached(): void
    {
        $plan = $this->plan(PreparedTicketResource::class, ['create']);

        $this->assertSame(['update 1', 'update 2', 'escalate 1', 'escalate 2'], self::described(GatePrefetch::page($plan, $this->rows(), GateReach::everyRow())));
    }

    #[Test]
    public function it_batches_a_cached_class_gate_of_a_resource_that_prepares(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $plan = $this->plan(CachedTicketResource::class, ['create']);

        $this->assertSame(['create class', 'update 1', 'update 2', 'escalate 1', 'escalate 2'], self::described(GatePrefetch::page($plan, $this->rows(), GateReach::everyRow())));
    }

    /** @param list<GateAbility>|null $gates */
    #[Test]
    #[DataProvider('noGates')]
    public function it_reads_neither_the_authorizer_nor_the_cache_without_gates(?array $gates, bool $cache): void
    {
        config(['rest.authorizations.cache.enabled' => $cache]);
        $resolutions = 0;
        $request = RestRequest::create('/api/tickets/search', 'POST');
        $request->setUserResolver(static function () use (&$resolutions): ?Authenticatable {
            $resolutions++;

            return null;
        });
        $plan = new QueryPlan((new RestContext($request, $this->app ?? $this->fail('No application.')))->resource(PreparedTicketResource::class), [], [], [], LimitPolicy::none(), gates: $gates);

        $this->assertSame([], GatePrefetch::page($plan, $this->rows(), GateReach::everyRow()));
        $this->assertSame(0, $resolutions);
    }

    /** @return iterable<string, array{list<GateAbility>|null, bool}> */
    public static function noGates(): iterable
    {
        yield 'no gates, cache off' => [null, false];
        yield 'no gates, cache on' => [null, true];
        yield 'an empty list, cache off' => [[], false];
        yield 'an empty list, cache on' => [[], true];
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
