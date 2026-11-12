<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\DeclarationKind;
use Lomkit\Rest\Http\Routing\DeclaredRoute;
use Lomkit\Rest\Http\Routing\RouteFingerprint;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\EndAttachmentAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\EndTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\OpenTicketCount;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UriKeyPropertyTest extends DeclarationTestCase
{
    #[Test]
    #[DataProvider('forms')]
    public function it_routes_an_action_by_its_uri_key_and_keeps_its_name(bool $byClass): void
    {
        DeskTicketResource::$byClass = $byClass;
        DeskTicketResource::$moreActions = [$byClass ? EndAttachmentAction::class : EndAttachmentAction::make()];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $route = $this->app['router']->getRoutes()->getByName('desk-tickets.operate.end');

        $this->assertNotNull($route);
        $this->assertSame('desk-tickets/actions/end', $route->uri());
        $this->assertSame(['action' => 'end'], $route->defaults);
        $this->assertSame(DeclaredRoute::ofInstance(new EndAttachmentAction)->fingerprint(), RouteFingerprint::onRoute($route));
        $this->postJson('/desk-tickets/actions/end')
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->getJson('/desk-tickets?with=actions')
            ->assertOk()
            ->assertJsonPath('data.actions.4.uriKey', 'end')
            ->assertJsonPath('data.actions.4.name', 'End Attachment');
    }

    #[Test]
    #[DataProvider('forms')]
    public function it_routes_a_metric_by_its_uri_key_and_keeps_its_name(bool $byClass): void
    {
        DeskTicketResource::$byClass = $byClass;
        DeskTicketResource::$moreMetrics = [$byClass ? OpenTicketCount::class : OpenTicketCount::make()];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $route = $this->app['router']->getRoutes()->getByName('desk-tickets.metric.open');

        $this->assertNotNull($route);
        $this->assertSame('desk-tickets/metrics/open', $route->uri());
        $this->assertSame(['metric' => 'open'], $route->defaults);
        $this->assertSame(DeclaredRoute::ofInstance(new OpenTicketCount)->fingerprint(), RouteFingerprint::onRoute($route));
        $this->postJson('/desk-tickets/metrics/open')
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 0, 'max' => 10]]);
        $this->getJson('/desk-tickets?with=metrics')
            ->assertOk()
            ->assertJsonPath('data.metrics.3.uriKey', 'open')
            ->assertJsonPath('data.metrics.3.name', 'Open Ticket Count');
    }

    #[Test]
    #[DataProvider('forms')]
    public function it_derives_the_key_from_the_name_without_a_uri_key(bool $byClass): void
    {
        DeskTicketResource::$byClass = $byClass;
        DeskTicketResource::$moreActions = [$byClass ? EndTickets::class : EndTickets::make()];
        Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register();

        $this->assertSame('desk-tickets/actions/end', $this->app['router']->getRoutes()->getByName('desk-tickets.operate.end')?->uri());
        $this->assertSame('end', DeclaredRoute::ofClass(EndTickets::class, DeclarationKind::Action)->key);
        $this->assertSame('close-tickets', DeclaredRoute::ofClass(CloseTickets::class, DeclarationKind::Action)->key);
    }

    #[Test]
    #[DataProvider('forms')]
    public function it_rejects_a_uri_key_that_another_declaration_uses(bool $byClass): void
    {
        DeskTicketResource::$byClass = $byClass;
        DeskTicketResource::$moreActions = $byClass ? [EndTickets::class, EndAttachmentAction::class] : [EndTickets::make(), EndAttachmentAction::make()];

        $this->expectExceptionObject(InvalidResourceRouteException::duplicateUri('POST desk-tickets/actions/end'));

        Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register();
    }

    /** @return array<string, array{bool}> */
    public static function forms(): array
    {
        return [
            'instances' => [false],
            'classes' => [true],
        ];
    }
}
