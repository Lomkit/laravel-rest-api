<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Illuminate\Routing\RouteCollection;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CompilesRoutes;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskCloseTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RememberedRoutesTest extends DeclarationTestCase
{
    use CompilesRoutes;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::forgetRoutes();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Rest::forgetRoutes();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('forms')]
    public function it_registers_a_remembered_resource_again_without_building_it(bool $byClass): void
    {
        DeskTicketResource::$byClass = $byClass;
        $this->app['config']->set('rest.routes.remember', true);
        $first = $this->registerDesk();

        $resolved = $this->recordResolutions(fn () => $this->registerDesk());

        $this->assertSame([], array_values(array_filter($resolved, fn (string $abstract): bool => in_array($abstract, ['request', DeskTicketResource::class], true))));
        $this->assertSame($first, $this->registered());
        $this->assertSame([], DeclarationLog::$built);
    }

    /** @return array<string, array{bool}> */
    public static function forms(): array
    {
        return [
            'instances' => [false],
            'classes' => [true],
        ];
    }

    #[Test]
    public function it_builds_the_resource_at_each_registration_by_default(): void
    {
        $first = $this->registerDesk();

        $resolved = $this->recordResolutions(fn () => $this->registerDesk());

        $this->assertSame(['request', DeskTicketResource::class], array_values(array_filter($resolved, fn (string $abstract): bool => in_array($abstract, ['request', DeskTicketResource::class], true))));
        $this->assertSame($first, $this->registered());
    }

    #[Test]
    public function it_builds_the_resource_again_after_the_routes_are_forgotten(): void
    {
        $this->app['config']->set('rest.routes.remember', true);
        $this->registerDesk();
        DeskTicketResource::$dropped = true;

        Rest::forgetRoutes();
        $resolved = $this->recordResolutions(fn () => $this->registerDesk());

        $this->assertContains(DeskTicketResource::class, $resolved);
        $this->assertArrayNotHasKey('desk-tickets.operate.refresh-tickets', $this->registered());
    }

    #[Test]
    public function it_keeps_the_routes_of_a_resource_until_they_are_forgotten(): void
    {
        $this->app['config']->set('rest.routes.remember', true);
        $this->registerDesk();
        DeskTicketResource::$dropped = true;

        $this->registerDesk();

        $this->assertArrayHasKey('desk-tickets.operate.refresh-tickets', $this->registered());
    }

    #[Test]
    public function it_serves_remembered_routes_from_the_route_cache(): void
    {
        $this->seedTickets();
        $this->app['config']->set('rest.routes.remember', true);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $resolved = $this->recordResolutions(function (): void {
            $this->app['router']->setRoutes(new RouteCollection);
            Rest::resource('desk-tickets', DeskTicketController::class)->register();
        });

        $this->useCompiledRoutes();

        $this->assertNotContains(DeskTicketResource::class, $resolved);
        $this->assertSame(['action' => 'close-tickets'], $this->app['router']->getRoutes()->getByName('desk-tickets.operate.close-tickets')?->defaults);
        $this->postJson('/desk-tickets/actions/close-tickets', ['search' => []])->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([DeskCloseTickets::class], DeclarationLog::$built);
    }

    /** @return array<string, array{string, array<string, mixed>, list<mixed>, list<string>}> */
    private function registerDesk(): array
    {
        $this->app['router']->setRoutes(new RouteCollection);

        Rest::resource('desk-tickets', DeskTicketController::class)
            ->middleware('verified')
            ->middlewareFor('operate', 'auth')
            ->register();

        return $this->registered();
    }

    /** @return array<string, array{string, array<string, mixed>, list<mixed>, list<string>}> */
    private function registered(): array
    {
        return [...$this->deskRoutes('operate'), ...$this->deskRoutes('metric')];
    }

    /**
     * @param  callable(): mixed  $register
     * @return list<string>
     */
    private function recordResolutions(callable $register): array
    {
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            $resolved[] = $abstract;
        });

        $register();

        return $resolved;
    }
}
