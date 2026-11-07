<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Exceptions\QueuedActionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\CastValues;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\DocumentPriority;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\FieldDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\FieldDocumentResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ReadDocumentFields;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

#[WithMigration('queue')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
class ReadingActionFieldsTest extends PackageTestCase
{
    private const array BODY = ['fields' => [
        ['name' => 'due_at', 'value' => '2026-12-01 10:00:00'],
        ['name' => 'copies', 'value' => '3'],
        ['name' => 'labels', 'value' => ['red', 'blue']],
        ['name' => 'note', 'value' => null],
    ]];

    private ArchiveUser $grace;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ReadDocumentFields::$runs = [];
        ReadDocumentFields::$batches = [];
        FieldDocumentResource::$target = ActionTarget::Search;
        Rest::resource('field-documents', FieldDocumentController::class)->register();
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $this->grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@staff.example', 'password' => 'secret']);

        foreach ([1 => 'Budget', 2 => 'Contract', 3 => 'Invoice', 4 => 'Payroll'] as $id => $title) {
            Document::create(['id' => $id, 'title' => $title]);
        }
    }

    #[Test]
    public function it_reads_the_inputs_as_cast_properties(): void
    {
        $fields = $this->runAction('read-document-fields');

        $this->assertInstanceOf(CarbonImmutable::class, $fields->due_at);
        $this->assertSame('2026-12-01 10:00:00', $fields->due_at->format('Y-m-d H:i:s'));
        $this->assertSame([DocumentPriority::Normal, 3, ['red', 'blue'], null, null], [$fields->priority, $fields->copies, $fields->labels, $fields->note, $fields->reason]);
    }

    #[Test]
    #[DataProvider('presence')]
    public function it_tells_a_missing_input_from_a_null_one(string $name, bool $has, bool $filled): void
    {
        $fields = $this->runAction('read-document-fields');

        $this->assertSame([$has, $filled, $filled], [$fields->has($name), $fields->filled($name), isset($fields->{$name})]);
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function presence(): array
    {
        return [
            'sent with a value' => ['copies', true, true],
            'sent as null' => ['note', true, false],
            'not sent, with a default' => ['priority', true, true],
            'not sent, without a default' => ['reason', false, false],
        ];
    }

    #[Test]
    public function it_gives_the_inputs_in_declaration_order(): void
    {
        $fields = $this->runAction('read-document-fields');

        $this->assertSame(['copies' => 3, 'note' => null], $fields->only('reason', 'note', 'copies'));
        $this->assertSame(['due_at', 'priority', 'copies', 'labels', 'note'], array_keys($fields->toArray()));
    }

    /** @param Closure(ActionFields): mixed $read */
    #[Test]
    #[DataProvider('undeclared')]
    public function it_refuses_an_input_that_the_action_does_not_declare(Closure $read): void
    {
        $fields = $this->runAction('read-document-fields');

        $this->expectExceptionObject(new InvalidFieldException(ReadDocumentFields::class.'::fields() does not declare the input [copy].'));

        $read($fields);
    }

    /** @return array<string, array{Closure(ActionFields): mixed}> */
    public static function undeclared(): array
    {
        return [
            'a property' => [fn (ActionFields $fields): mixed => $fields->copy],
            'isset()' => [fn (ActionFields $fields): bool => isset($fields->copy)],
            'has()' => [fn (ActionFields $fields): bool => $fields->has('copy')],
            'filled()' => [fn (ActionFields $fields): bool => $fields->filled('copy')],
            'only()' => [fn (ActionFields $fields): array => $fields->only('copies', 'copy')],
        ];
    }

    #[Test]
    #[DataProvider('runs')]
    public function it_gives_the_user_and_the_target_of_the_run(?int $user, ActionTarget $target, int $handled): void
    {
        FieldDocumentResource::$target = $target;

        if ($user !== null) {
            $this->actingAs(ArchiveUser::query()->findOrFail($user));
        }

        $fields = $this->runAction('read-document-fields');

        $this->assertSame([$user, $target], [$fields->user()?->getAuthIdentifier(), $fields->target()]);
        $this->assertCount($handled, ReadDocumentFields::$runs[0]['keys']);
    }

    /** @return array<string, array{?int, ActionTarget, int}> */
    public static function runs(): array
    {
        return [
            'a user on the search target' => [2, ActionTarget::Search, 4],
            'a user on the none target' => [2, ActionTarget::None, 0],
            'a guest on the search target' => [null, ActionTarget::Search, 3],
        ];
    }

    #[Test]
    public function it_gives_the_worker_the_values_the_user_and_the_target_of_the_request(): void
    {
        FieldDocumentResource::$target = ActionTarget::Resources;
        $this->actingAs($this->grace);
        Queue::fake();
        $this->postJson('/field-documents/actions/queued-read-document-fields', ['resources' => [2, 4], ...self::BODY])->assertOk();

        $this->work(Queue::pushed(QueuedAction::class)->sole());

        $fields = ReadDocumentFields::$runs[0]['fields'];
        $this->assertSame([[2, 4]], array_column(ReadDocumentFields::$runs, 'keys'));
        $this->assertSame([2, ActionTarget::Resources, DocumentPriority::Normal], [$fields->user()?->getAuthIdentifier(), $fields->target(), $fields->priority]);
        $this->assertEquals(self::cast(), $fields->toArray());
    }

    #[Test]
    #[DataProvider('users')]
    public function it_keeps_the_values_the_target_and_the_user_through_serialization(?int $user): void
    {
        if ($user !== null) {
            $this->actingAs(ArchiveUser::query()->findOrFail($user));
        }

        $serialized = serialize($this->runAction('read-document-fields'));
        $this->app['auth']->forgetGuards();
        $fields = unserialize($serialized);

        preg_match_all('/O:\d+:"([^"]+)"/', $serialized, $objects);
        $this->assertSame([ActionFields::class, CastValues::class, CarbonImmutable::class, UserReference::class], $objects[1]);
        $this->assertInstanceOf(ActionFields::class, $fields);
        $this->assertEquals(self::cast(), $fields->toArray());
        $this->assertSame([DocumentPriority::Normal, ActionTarget::Search, $user], [$fields->priority, $fields->target(), $fields->user()?->getAuthIdentifier()]);
    }

    /** @return array<string, array{?int}> */
    public static function users(): array
    {
        return [
            'a user' => [2],
            'a guest' => [null],
        ];
    }

    #[Test]
    public function it_fails_to_read_back_the_fields_of_a_user_that_no_longer_exists(): void
    {
        $this->actingAs($this->grace);
        $serialized = serialize($this->runAction('read-document-fields'));
        $this->grace->delete();
        $this->app['auth']->forgetGuards();

        $this->expectExceptionObject(QueuedActionException::missingUser('web', ArchiveUser::class, 2));

        unserialize($serialized);
    }

    #[Test]
    public function it_gives_the_same_fields_to_with_batch_and_to_the_jobs(): void
    {
        $this->actingAs($this->grace);
        Str::createUuidsUsingSequence([Uuid::fromString('9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f')]);

        $this->postJson('/field-documents/actions/batched-read-document-fields', self::BODY)
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 4, 'batch' => '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f', 'result' => null]]);

        $this->assertCount(1, ReadDocumentFields::$batches);
        $this->assertSame([[1, 2, 3, 4]], array_column(ReadDocumentFields::$runs, 'keys'));

        foreach ([ReadDocumentFields::$batches[0], ReadDocumentFields::$runs[0]['fields']] as $fields) {
            $this->assertEquals(self::cast(), $fields->toArray());
            $this->assertSame([2, ActionTarget::Search], [$fields->user()?->getAuthIdentifier(), $fields->target()]);
        }
    }

    #[Test]
    public function it_gives_every_chunk_the_same_fields(): void
    {
        $this->actingAs($this->grace);

        $this->postJson('/field-documents/actions/chunked-read-document-fields', self::BODY)
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);

        $this->assertSame([[1, 2], [3, 4]], array_column(ReadDocumentFields::$runs, 'keys'));
        $this->assertSame(ReadDocumentFields::$runs[0]['fields'], ReadDocumentFields::$runs[1]['fields']);
        $this->assertSame(3, ReadDocumentFields::$runs[1]['fields']->copies);
    }

    /** @return array<string, mixed> */
    private static function cast(): array
    {
        return [
            'due_at' => CarbonImmutable::parse('2026-12-01 10:00:00'),
            'priority' => DocumentPriority::Normal,
            'copies' => 3,
            'labels' => ['red', 'blue'],
            'note' => null,
        ];
    }

    private function runAction(string $action): ActionFields
    {
        $this->postJson("/field-documents/actions/{$action}", self::BODY)->assertOk();

        return ReadDocumentFields::$runs[0]['fields'];
    }

    private function work(QueuedAction $job): void
    {
        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));

        $this->app->call([$worker, 'handle']);
    }
}
