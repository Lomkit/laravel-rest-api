<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Authorization;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ContextTagTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstCacheContext;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class ReachedCacheContextTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FirstCacheContext::reset();
        Rest::resource('context-tag-tickets', ContextTagTicketController::class)->register();
        Team::forceCreate(['id' => 1, 'name' => 'Support']);
        $ticket = Ticket::forceCreate(['id' => 1, 'team_id' => 1, 'title' => 'Ticket 1', 'status' => 'open']);
        Tag::forceCreate(['id' => 1, 'name' => 'Secret']);
        $ticket->tags()->attach(1);
    }

    #[\Override]
    protected function tearDown(): void
    {
        FirstCacheContext::reset();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('scopes')]
    public function it_keys_the_extension_items_of_each_reached_resource(string $metric, string $seen, string $hidden): void
    {
        config(['rest.extensions' => ['tags' => FirstCacheContext::class]]);
        FirstCacheContext::$resources = [TagResource::class];
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('reveal', config()->boolean('helpdesk.reveal_secret_tags'));
        Tag::addGlobalScope('secret', fn (Builder $query): Builder => config()->boolean('helpdesk.reveal_secret_tags') ? $query : $query->where('tags.name', '!=', 'Secret'));
        $contents = [];

        foreach ([false, true, false] as $reveal) {
            config(['helpdesk.reveal_secret_tags' => $reveal]);
            $contents[] = $this->actingAs(new GenericUser(['id' => 1]))
                ->postJson("/context-tag-tickets/metrics/{$metric}", ['search' => ['filters' => [['field' => 'tags.name', 'value' => 'Secret']]]])
                ->assertOk()
                ->getContent();
        }

        $this->assertSame([$hidden, $seen, $hidden], $contents);
        $this->assertCount(3, FirstCacheContext::$contexts);
        $this->assertInstanceOf(TagResource::class, FirstCacheContext::$contexts[0]->resource);
    }

    /** @return array<string, array{string, string, string}> */
    public static function scopes(): array
    {
        return [
            'each user' => ['ticket-backlog', '{"data":{"type":"gauge","value":1,"max":10}}', '{"data":{"type":"gauge","value":0,"max":10}}'],
            'every user' => ['row-count', '{"data":{"type":"count","value":1}}', '{"data":{"type":"count","value":0}}'],
        ];
    }
}
