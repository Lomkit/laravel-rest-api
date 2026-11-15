<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Authorization;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use LogicException;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FolderVersions;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\LateFolderVersions;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondFolderVersions;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\VersionLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class VersionsRowChecksTest extends HelpdeskTestCase
{
    private const array SEARCH = ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]];

    private const array FIRST = ['search' => ['filters' => [['field' => 'id', 'value' => 1]], 'gates' => ['update']]];

    private const string CREATED = '1793959200.000000';

    /** @var list<string> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::reset();
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
        $this->travelTo('2026-11-06 10:00:05');
    }

    #[\Override]
    protected function tearDown(): void
    {
        self::reset();

        parent::tearDown();
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_checks_a_row_again_once_the_version_of_an_extension_changes(): void
    {
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        $this->travel(1)->seconds();
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        Team::query()->findOrFail(2)->touch();
        $this->travel(1)->seconds();
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();

        $versions = 'versions folder update 1,2 for guest';
        $this->assertSame(['viewAny', $versions, 'update 1', 'update 2', $versions, $versions, 'update 2'], $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_hands_the_extensions_the_object_of_the_hook_after_the_hook(): void
    {
        $this->postJson('/versioned-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', 'versions hook update 1,2 for guest', 'versions folder update 1,2 for guest', 'update 1', 'update 2'], $this->tickets->calls);
        $this->assertCount(1, FolderVersions::$received);
        $this->assertSame(VersionedTicketResource::$received[0], FolderVersions::$received[0]);
    }

    /** @param list<class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('configOrders')]
    public function it_builds_one_key_from_the_hook_and_the_extensions_in_any_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        Cache::forever('rest.authorizations.stamp', 'every-user');
        SecondFolderVersions::$version = static function (RowVersions $versions): void {
            $versions->add($versions->rows->firstOrFail(), 'grant', 'reader');
        };

        $this->postJson('/versioned-tickets/search', self::FIRST)->assertOk();

        $this->assertSame(['viewAny', 'versions hook update 1 for guest', 'versions a update 1 for guest', 'versions b update 1 for guest', 'update 1'], $this->tickets->calls);
        $versions = serialize(['a.grant' => 'reader', 'b.team' => self::CREATED, 'team' => self::CREATED]);
        $this->assertSame(self::key(VersionedTicketResource::class.'|update|'.Ticket::class.'#1|'.self::CREATED.$versions.'|a:0:{}'), $this->written[1]);
    }

    /** @return iterable<string, array{list<class-string<Extension>>}> */
    public static function configOrders(): iterable
    {
        yield 'b before a' => [[LateFolderVersions::class, SecondFolderVersions::class]];
        yield 'a before b' => [[SecondFolderVersions::class, LateFolderVersions::class]];
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_versions_only_the_resources_that_an_extension_applies_to(): void
    {
        Cache::forever('rest.authorizations.stamp', 'every-user');
        FolderVersions::$resources = [TeamResource::class];

        $this->postJson('/api/cached-tickets/search', self::FIRST)->assertOk();
        FolderVersions::$resources = [CachedTicketResource::class];
        $this->postJson('/api/cached-tickets/search', self::FIRST)->assertOk();

        $this->assertSame(['viewAny', 'update 1', 'versions folder update 1 for guest', 'update 1'], $this->tickets->calls);
        $this->assertSame([
            self::key(CachedTicketResource::class.'|viewAny|'.Ticket::class.'|a:0:{}'),
            self::key(CachedTicketResource::class.'|update|'.Ticket::class.'#1|'.self::CREATED.'|a:0:{}'),
            self::key(CachedTicketResource::class.'|update|'.Ticket::class.'#1|'.self::CREATED.serialize(['folder.team' => self::CREATED]).'|a:0:{}'),
        ], $this->written);
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_never_versions_an_ability_that_the_resource_does_not_keep(): void
    {
        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['escalate', 'update'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame(['viewAny', 'versions folder update 1,2 for guest', 'escalate 1', 'update 1', 'escalate 2', 'update 2'], $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_hands_the_extension_the_user_of_the_gate(): void
    {
        $lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $other = new GenericUser(['id' => 9]);

        $this->actingAs($lead)->postJson('/api/cached-tickets/search', self::FIRST)->assertOk();
        $this->actingAs(new GenericUser(['id' => 7]));
        Rest::search(CachedTicketResource::class)->as($other)->payload(self::FIRST['search'])->render();

        $this->assertCount(2, FolderVersions::$received);
        $this->assertSame($lead, FolderVersions::$received[0]->user);
        $this->assertSame($other, FolderVersions::$received[1]->user);
        $this->assertInstanceOf(CachedTicketResource::class, FolderVersions::$received[0]->resource);
        $this->assertInstanceOf(Collection::class, FolderVersions::$received[0]->rows);
        $this->assertSame([1], FolderVersions::$received[0]->rows->modelKeys());
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_lets_the_exception_of_an_extension_through_before_any_policy_check(): void
    {
        FolderVersions::$version = static function (): void {
            throw new LogicException('The grants are unreachable.');
        };
        $context = $this->contextFor(null);

        try {
            $context->authorizer()->inspectEach($context->resource(CachedTicketResource::class), 'update', Ticket::query()->orderBy('id')->get());
            $this->fail('The extension did not throw.');
        } catch (LogicException $exception) {
            $this->assertSame('The grants are unreachable.', $exception->getMessage());
        }

        $this->assertSame(['versions folder update 1,2 for guest'], $this->tickets->calls);
    }

    /** @param Closure(RowVersions): mixed $change */
    #[Test]
    #[DataProvider('rowChanges')]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_refuses_an_extension_that_changes_a_row(Closure $change): void
    {
        FolderVersions::$version = static function (RowVersions $versions) use ($change): void {
            $change($versions);
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedVersionedRows('folder', FolderVersions::class, CachedTicketResource::class, 'update'));

        try {
            $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['update'], 'includes' => [['relation' => 'team']], 'sorts' => [['field' => 'id']]]]);
        } finally {
            $this->assertSame(['viewAny', 'versions folder update 1,2 for guest'], $this->tickets->calls);
        }
    }

    /** @return iterable<string, array{Closure(RowVersions): mixed}> */
    public static function rowChanges(): iterable
    {
        yield 'an attribute' => [static fn (RowVersions $versions): Model => $versions->rows->firstOrFail()->setAttribute('title', 'Changed')];
        yield 'a loaded relation' => [static fn (RowVersions $versions): Model => $versions->rows->firstOrFail()->setRelation('team', new Team(['name' => 'Other']))];
        yield 'the list of rows' => [static fn (RowVersions $versions): ?Model => $versions->rows->pop()];
    }

    #[Test]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_names_the_registered_class_of_a_versioning_extension_bound_to_a_subclass(): void
    {
        $this->app?->bind(FolderVersions::class, static fn (): FolderVersions => new class extends FolderVersions {});
        FolderVersions::$version = static function (RowVersions $versions): void {
            $versions->add(Ticket::query()->findOrFail(1), 'team', 1);
        };
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/cached-tickets/search', self::SEARCH);
            $this->fail('The unknown row was accepted.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertSame(InvalidAuthorizationExtensionException::unknownVersionedRow('folder', FolderVersions::class, CachedTicketResource::class, 'update', Ticket::class)->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(FolderVersions::class));
        }

        $this->assertSame(['viewAny', 'versions folder update 1,2 for guest'], $this->tickets->calls);
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    #[WithConfig('rest.extensions', [FolderVersions::class])]
    public function it_refuses_a_version_key_that_the_cache_context_refuses(string $key): void
    {
        FolderVersions::$version = static function (RowVersions $versions) use ($key): void {
            $versions->add($versions->rows->firstOrFail(), $key, 1);
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::invalidVersionKey('folder', FolderVersions::class, CachedTicketResource::class, $key));

        $this->postJson('/api/cached-tickets/search', self::SEARCH);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'an empty key' => [''];
        yield 'a key that starts with a digit' => ['1team'];
        yield 'a dotted key' => ['team.id'];
        yield 'a key of 65 characters' => [str_repeat('a', 65)];
    }

    private static function reset(): void
    {
        VersionLog::reset();
        FolderVersions::$resources = null;
        FolderVersions::$received = [];
        FolderVersions::$version = null;
        SecondFolderVersions::$received = [];
        SecondFolderVersions::$version = null;
    }

    private static function key(string $parts): string
    {
        return 'rest.authorizations.'.hash('sha256', 'every-user|web|guest|'.$parts);
    }
}
