<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\DenialsRenderer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ChannelPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReplyPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class HelpdeskTestCase extends PackageTestCase
{
    protected TicketPolicy $tickets;

    protected TeamPolicy $teams;

    protected TagPolicy $tags;

    protected ChannelPolicy $channels;

    protected ReplyPolicy $replies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(TicketPolicy::class, $this->tickets = new TicketPolicy);
        $this->app->instance(TeamPolicy::class, $this->teams = new TeamPolicy);
        $this->app->instance(TagPolicy::class, $this->tags = new TagPolicy);
        $this->app->instance(ChannelPolicy::class, $this->channels = new ChannelPolicy);
        $this->app->instance(ReplyPolicy::class, $this->replies = new ReplyPolicy);
    }

    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', false);
    }

    /** @return array<string, Ticket> */
    protected function seedTickets(): array
    {
        $billing = Team::create(['name' => 'Billing']);
        $support = Team::create(['name' => 'Support']);

        return [
            'Printer jam' => Ticket::create(['team_id' => $billing->id, 'title' => 'Printer jam', 'status' => 'open']),
            'Password reset' => Ticket::create(['team_id' => $support->id, 'title' => 'Password reset', 'status' => 'closed']),
            'Printer toner' => Ticket::create(['team_id' => $billing->id, 'title' => 'Printer toner', 'status' => 'open']),
            'Laptop setup' => Ticket::create(['team_id' => $support->id, 'title' => 'Laptop setup', 'status' => 'closed']),
        ];
    }

    /** @return list<string> */
    protected function calls(string $ability, Model ...$models): array
    {
        return array_map(fn (Model $model): string => $ability.' '.$model->getKey(), $models);
    }

    protected function contextFor(?Authenticatable $user): RestContext
    {
        $request = RestRequest::create('/api/tickets/search', 'POST');
        $request->setUserResolver(fn (): ?Authenticatable => $user);

        return new RestContext($request, $this->app);
    }

    protected function renderDenials(): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(new DenialsRenderer);
    }

    protected function logQueries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    protected function assertNoQueries(): void
    {
        $this->assertCount(0, DB::getQueryLog());
    }
}
