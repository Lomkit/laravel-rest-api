<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use Lomkit\Rest\Authorization\CacheDecisionStore;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTagResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\VersionLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class DecisionVersionsTest extends HelpdeskTestCase
{
    private const array SEARCH = ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]];

    private const string CREATED = '1793959200.000000';

    private const string HOOK = 'versions hook update 1,2,3 for guest';

    /** @var list<string> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        VersionLog::reset();
        Rest::resource('versioned-tickets', VersionedTicketController::class)->register();
        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            if ($event->seconds !== null && str_starts_with($event->key, 'rest.authorizations.')) {
                $this->written[] = $event->key;
            }
        });
        $this->travelTo('2026-11-06 10:00:00');
        Team::forceCreate(['id' => 1, 'name' => 'Billing']);
        Team::forceCreate(['id' => 2, 'name' => 'Support']);
        Ticket::forceCreate(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Ticket::forceCreate(['id' => 2, 'team_id' => 2, 'title' => 'Password reset', 'status' => 'open']);
        Ticket::forceCreate(['id' => 3, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'closed']);
        $this->travelTo('2026-11-06 10:00:05');
    }

    #[\Override]
    protected function tearDown(): void
    {
        VersionLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_keeps_the_key_of_a_decision_byte_for_byte_without_a_hook_or_an_extension(): void
    {
        Cache::forever('rest.authorizations.stamp', 'every-user');

        $this->postJson('/api/cached-tickets/search', ['search' => ['filters' => [['field' => 'id', 'value' => 1]], 'gates' => ['update']]])->assertOk();

        $this->assertSame([
            self::key(CachedTicketResource::class.'|viewAny|'.Ticket::class.'|a:0:{}'),
            self::key(CachedTicketResource::class.'|update|'.Ticket::class.'#1|'.self::CREATED.'|a:0:{}'),
        ], $this->written);
    }

    #[Test]
    public function it_adds_the_versions_of_the_hook_to_the_key_of_their_row(): void
    {
        Cache::forever('rest.authorizations.stamp', 'every-user');

        $this->postJson('/versioned-tickets/search', ['search' => ['filters' => [['field' => 'id', 'value' => 1]], 'gates' => ['update']]])->assertOk();

        $this->assertSame([
            self::key(VersionedTicketResource::class.'|viewAny|'.Ticket::class.'|a:0:{}'),
            self::key(VersionedTicketResource::class.'|update|'.Ticket::class.'#1|'.self::CREATED.serialize(['team' => self::CREATED]).'|a:0:{}'),
        ], $this->written);
    }

    #[Test]
    public function it_reuses_a_kept_decision_while_the_versions_of_its_row_stay(): void
    {
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();
        $this->travel(1)->seconds();
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', self::HOOK, 'update 1', 'update 2', 'update 3', self::HOOK], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_a_row_again_once_a_version_of_it_changes(): void
    {
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();
        $this->travel(1)->seconds();
        Team::query()->findOrFail(1)->touch();
        $this->travel(1)->seconds();
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', self::HOOK, 'update 1', 'update 2', 'update 3', self::HOOK, 'update 1', 'update 3'], $this->tickets->calls);
    }

    #[Test]
    public function it_keeps_no_decision_on_a_row_whose_version_is_from_the_current_second(): void
    {
        Team::query()->findOrFail(1)->touch();

        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', self::HOOK, 'update 1', 'update 2', 'update 3', self::HOOK, 'update 1', 'update 3'], $this->tickets->calls);
    }

    #[Test]
    public function it_keeps_no_decision_on_a_row_given_two_values_for_one_key(): void
    {
        VersionedTicketResource::$version = static function (RowVersions $versions): void {
            foreach ($versions->rows as $row) {
                $versions->add($row, 'grant', 7);
                $versions->add($row, 'grant', $row->getKey() === 2 ? 8 : 7);
            }
        };

        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', self::HOOK, 'update 1', 'update 2', 'update 3', self::HOOK, 'update 2'], $this->tickets->calls);
    }

    #[Test]
    public function it_collects_the_versions_once_per_kept_ability_before_the_cache_lookup(): void
    {
        $this->postJson('/versioned-tickets/search', ['search' => ['gates' => ['update', 'escalate', 'delete'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([
            'viewAny',
            self::HOOK,
            'versions hook delete 1,2,3 for guest',
            'update 1', 'escalate 1', 'delete 1',
            'update 2', 'escalate 2', 'delete 2',
            'update 3', 'escalate 3', 'delete 3',
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_reads_and_writes_a_decision_with_the_same_versions(): void
    {
        $calls = 0;
        VersionedTicketResource::$version = static function (RowVersions $versions) use (&$calls): void {
            $calls++;

            foreach ($versions->rows as $row) {
                $versions->add($row, 'grant', $calls);
            }
        };
        $read = [];
        Event::listen(RetrievingManyKeys::class, function (RetrievingManyKeys $event) use (&$read): void {
            foreach ($event->keys as $index => $key) {
                $read[] = is_string($index) ? $index : $key;
            }
        });

        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(1, $calls);
        $this->assertCount(4, $this->written);
        $this->assertSame([], array_diff($this->written, $read));
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();
        $this->assertSame(['viewAny', self::HOOK, 'update 1', 'update 2', 'update 3', self::HOOK, 'update 1', 'update 2', 'update 3'], $this->tickets->calls);
    }

    #[Test]
    public function it_versions_only_while_the_cache_keeps_decisions(): void
    {
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();
        config(['rest.authorizations.cache.enabled' => false]);
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', self::HOOK, 'update 1', 'update 2', 'update 3', 'viewAny', 'update 1', 'update 2', 'update 3'], $this->tickets->calls);
    }

    #[Test]
    public function it_hands_the_hook_the_user_of_the_gate(): void
    {
        $lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $other = new GenericUser(['id' => 9]);
        $search = ['filters' => [['field' => 'id', 'value' => 1]], 'gates' => ['update']];

        $this->actingAs($lead)->postJson('/versioned-tickets/search', ['search' => $search])->assertOk();
        $this->actingAs(new GenericUser(['id' => 7]));
        Rest::search(VersionedTicketResource::class)->as($other)->payload($search)->render();

        $this->assertCount(2, VersionedTicketResource::$received);
        $this->assertSame($lead, VersionedTicketResource::$received[0]->user);
        $this->assertSame($other, VersionedTicketResource::$received[1]->user);
        $this->assertSame('update', VersionedTicketResource::$received[0]->ability);
        $this->assertInstanceOf(VersionedTicketResource::class, VersionedTicketResource::$received[0]->resource);
        $this->assertInstanceOf(Collection::class, VersionedTicketResource::$received[0]->rows);
    }

    #[Test]
    public function it_hands_the_resource_of_the_check_every_row_of_an_app_check_on_two_models(): void
    {
        $tag = Tag::forceCreate(['id' => 1, 'name' => 'Hardware', 'created_at' => '2026-11-06 10:00:00', 'updated_at' => '2026-11-06 10:00:00']);
        $owner = 1;
        VersionedTicketResource::$version = static function (RowVersions $versions) use (&$owner): void {
            foreach ($versions->rows as $row) {
                $versions->add($row, 'owner', $row instanceof Tag ? $owner : 0);
            }
        };

        foreach ([1, 1, 2] as $owner) {
            $resource = $this->contextFor(null)->resource(VersionedTicketResource::class);
            $resource->context()->authorizer()->inspect(new Check($resource, 'attachTag', Ticket::query()->findOrFail(1), related: [$tag]));
        }

        $this->assertSame([
            'versions hook attachTag 1,1 for guest', 'attachTag 1 1',
            'versions hook attachTag 1,1 for guest',
            'versions hook attachTag 1,1 for guest', 'attachTag 1 1',
        ], $this->tickets->calls);
        $this->assertSame([Ticket::class, Tag::class], VersionedTicketResource::$received[0]->rows->map(fn (object $row): string => $row::class)->all());
    }

    #[Test]
    public function it_versions_the_related_row_of_a_link_check_with_its_own_resource(): void
    {
        Tag::forceCreate(['id' => 1, 'name' => 'Hardware', 'created_at' => '2026-11-06 10:00:00', 'updated_at' => '2026-11-06 10:00:00']);
        $attach = ['mutate' => [['operation' => 'update', 'key' => 1, 'relations' => ['tags' => [['operation' => 'attach', 'key' => 1]]]]]];

        foreach ([1, 1, 2] as $group) {
            VersionedTagResource::$groups = [1 => $group];
            $this->travel(1)->seconds();
            $this->postJson('/versioned-tickets/mutate', $attach)->assertOk();
            DB::table('tag_ticket')->delete();
        }

        $versions = 'versions hook attachTag 1 for guest';
        $this->assertSame([
            'viewAny', $versions, 'update 1', 'attachTag 1 1',
            $versions, 'update 1',
            $versions, 'update 1', 'attachTag 1 1',
        ], $this->tickets->calls);
        $this->assertSame(array_fill(0, 3, 'versions tag attachTag 1 for guest'), array_values(array_filter($this->tags->calls, fn (string $call): bool => str_starts_with($call, 'versions '))));
    }

    #[Test]
    public function it_runs_no_hook_for_a_store_built_without_versions(): void
    {
        Cache::forever('rest.authorizations.stamp', 'every-user');
        $resource = $this->contextFor(null)->resource(VersionedTicketResource::class);
        $store = new CacheDecisionStore($this->app->make(Factory::class), UserIdentity::of('web', null));
        $check = new Check($resource, 'update', Ticket::query()->findOrFail(1));

        $store->many([$check->key() => $check]);
        $store->putMany([$check->key() => $check], [$check->key() => Decision::allow()]);

        $this->assertCount(0, VersionedTicketResource::$received);
        $this->assertSame([self::key(VersionedTicketResource::class.'|update|'.Ticket::class.'#1|'.self::CREATED.'|a:0:{}')], $this->written);
    }

    #[Test]
    public function it_lets_the_exception_of_the_hook_through_before_any_policy_check(): void
    {
        VersionedTicketResource::$version = static function (): void {
            throw new LogicException('The folders are unreachable.');
        };
        $context = $this->contextFor(null);

        try {
            $context->authorizer()->inspectEach($context->resource(VersionedTicketResource::class), 'update', Ticket::query()->orderBy('id')->get());
            $this->fail('The hook did not throw.');
        } catch (LogicException $exception) {
            $this->assertSame('The folders are unreachable.', $exception->getMessage());
        }

        $this->assertSame([self::HOOK], $this->tickets->calls);
    }

    #[Test]
    public function it_refuses_a_hook_that_changes_a_row(): void
    {
        VersionedTicketResource::$version = static function (RowVersions $versions): void {
            $versions->rows->firstOrFail()->setAttribute('title', 'Changed');
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidResourceException::changedVersionedRows(VersionedTicketResource::class, 'update'));

        try {
            $this->postJson('/versioned-tickets/search', self::SEARCH);
        } finally {
            $this->assertSame(['viewAny', self::HOOK], $this->tickets->calls);
        }
    }

    #[Test]
    public function it_refuses_a_version_from_the_hook_for_a_row_that_it_does_not_receive(): void
    {
        VersionedTicketResource::$version = static function (RowVersions $versions): void {
            $versions->add(Ticket::query()->findOrFail(1), 'team', 1);
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidResourceException::unknownVersionedRow(VersionedTicketResource::class, 'update', Ticket::class));

        $this->postJson('/versioned-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_refuses_a_version_key_of_the_hook_that_the_cache_context_refuses(): void
    {
        VersionedTicketResource::$version = static function (RowVersions $versions): void {
            $versions->add($versions->rows->firstOrFail(), 'team.id', 1);
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidResourceException::invalidVersionKey(VersionedTicketResource::class, 'team.id'));

        $this->postJson('/versioned-tickets/search', self::SEARCH);
    }

    private static function key(string $parts): string
    {
        return 'rest.authorizations.'.hash('sha256', 'every-user|web|guest|'.$parts);
    }
}
