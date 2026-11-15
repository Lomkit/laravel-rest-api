<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Writes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTransaction;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Events\ResourceRestored;
use Lomkit\Rest\Exceptions\InvalidWriteExtensionException;
use Lomkit\Rest\Exceptions\WriteRefused;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Http\WriteTransaction;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveDocumentsByOne;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveEachDocument;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AtomicArchiveDocuments;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DenyingDocumentPolicy;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DirectArchiveDocuments;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FrozenGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ModeChangingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WrittenDocumentController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WrittenDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
#[WithConfig('app.debug', false)]
final class GuardedWritesTest extends PackageTestCase
{
    use SeedsDocuments;

    private const string TRASHED = '2026-01-01 00:00:00';

    private const array MUTATE = ['mutate' => [
        ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Annual summary']],
        ['operation' => 'create', 'attributes' => ['title' => 'Minutes']],
    ]];

    private ArchiveUser $grace;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();
        WrittenDocumentResource::$confirming = false;
        config(['rest.extensions' => ['quota' => RecordingGuard::class]]);
        Rest::resource('written-documents', WrittenDocumentController::class)
            ->withSoftDeletes()
            ->register();
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $this->grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com', 'password' => 'secret']);
        $this->actingAs($this->grace);
    }

    #[\Override]
    protected function tearDown(): void
    {
        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();
        WrittenDocumentResource::$confirming = false;

        parent::tearDown();
    }

    #[Test]
    public function it_guards_a_mutation_after_its_checks_and_before_the_first_write(): void
    {
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson('/written-documents/mutate', self::MUTATE)->assertOk();

        $this->assertSame([
            'begin level 2',
            'guard mutate update,create',
            'mutatingAll update,create',
            'sql update documents',
            'sql insert documents',
            'mutatedAll 2',
            'commit level 1',
        ], $this->writeLines());
        $context = WriteLog::$guards[0];
        $this->assertCount(1, WriteLog::$guards);
        $this->assertInstanceOf(WrittenDocumentResource::class, $context->resource);
        $this->assertSame(Endpoint::Mutate, $context->endpoint);
        $this->assertSame(2, $context->user?->getAuthIdentifier());
        $this->assertSame(
            [[MutationOperation::Update, 1, ['title' => 'Annual summary']], [MutationOperation::Create, null, ['title' => 'Minutes']]],
            array_map(fn (MutationNode $entry): array => [$entry->operation, $entry->key, $entry->attributes], $context->entries ?? []),
        );
        $this->assertSame([[1, 'Annual report']], $this->keysAndTitles($context->rows));
        $this->assertNull($context->action);
        $this->assertNull($context->fields);
    }

    /** @return array<string, array{string, string, ?string, Endpoint, list<string>}> */
    public static function rowBatches(): array
    {
        return [
            'destroy' => ['DELETE', '/written-documents', null, Endpoint::Destroy, ['destroyingAll 1,2', 'sql update documents', 'sql update documents', 'destroyedAll 1,2']],
            'restore' => ['POST', '/written-documents/restore', self::TRASHED, Endpoint::Restore, ['restoringAll 1,2', 'sql update documents', 'sql update documents', 'restoredAll 1,2']],
            'force destroy' => ['DELETE', '/written-documents/force', self::TRASHED, Endpoint::ForceDestroy, ['forceDestroyingAll 1,2', 'sql delete documents', 'sql delete documents', 'forceDestroyedAll 1,2']],
        ];
    }

    /** @param list<string> $writes */
    #[Test]
    #[DataProvider('rowBatches')]
    public function it_guards_each_endpoint_of_a_row_batch(string $method, string $uri, ?string $deletedAt, Endpoint $endpoint, array $writes): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        EventLog::listen();

        $this->json($method, $uri, ['resources' => [1, 2]])->assertOk();

        $this->assertSame(['begin level 2', "guard {$endpoint->value} 1,2", ...$writes, 'commit level 1'], $this->writeLines());
        $this->assertCount(1, WriteLog::$guards);
        $this->assertSame($endpoint, WriteLog::$guards[0]->endpoint);
        $this->assertSame(2, WriteLog::$guards[0]->user?->getAuthIdentifier());
        $this->assertSame([[1, 'Annual report'], [2, 'Budget']], $this->keysAndTitles(WriteLog::$guards[0]->rows));
        $this->assertNull(WriteLog::$guards[0]->entries);
    }

    /** @return array<string, array{string, class-string, list<string>, list<list<int>|null>}> */
    public static function actions(): array
    {
        return [
            'chunk' => ['archive-documents-by-one', ArchiveDocumentsByOne::class, [
                'operating archive-documents-by-one',
                'begin level 2', 'guard operate 1', 'handle 1', 'sql update documents', 'commit level 1',
                'begin level 2', 'guard operate 2', 'handle 2', 'sql update documents', 'commit level 1',
                'operated archive-documents-by-one 2',
            ], [[1], [2]]],
            'atomic' => ['atomic-archive-documents', AtomicArchiveDocuments::class, [
                'begin level 2', 'guard operate *',
                'operating atomic-archive-documents',
                'handle 1', 'sql update documents', 'handle 2', 'sql update documents',
                'operated atomic-archive-documents 2',
                'commit level 1',
            ], [null]],
            'direct' => ['direct-archive-documents', DirectArchiveDocuments::class, [
                'operating direct-archive-documents',
                'guard operate 1', 'handle 1', 'sql update documents',
                'guard operate 2', 'handle 2', 'sql update documents',
                'operated direct-archive-documents 2',
            ], [[1], [2]]],
            'per row' => ['archive-each-document', ArchiveEachDocument::class, [
                'operating archive-each-document',
                'before 1', 'begin level 2', 'guard operate 1', 'row 1', 'sql update documents', 'commit level 1',
                'before 2', 'begin level 2', 'guard operate 2', 'row 2', 'sql update documents', 'commit level 1',
                'operated archive-each-document 2',
            ], [[1], [2]]],
        ];
    }

    /**
     * @param  class-string  $action
     * @param  list<string>  $lines
     * @param  list<list<int>|null>  $rows
     */
    #[Test]
    #[DataProvider('actions')]
    public function it_guards_each_transaction_of_an_action(string $uriKey, string $action, array $lines, array $rows): void
    {
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson("/written-documents/actions/{$uriKey}", ['search' => []])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);

        $this->assertSame($lines, $this->writeLines());
        $this->assertSame($rows, array_map(fn ($context): ?array => $context->rows === null ? null : array_map(fn (Model $row): mixed => $row->getKey(), $context->rows), WriteLog::$guards));

        foreach (WriteLog::$guards as $context) {
            $this->assertSame(Endpoint::Operate, $context->endpoint);
            $this->assertInstanceOf($action, $context->action);
            $this->assertInstanceOf(ActionFields::class, $context->fields);
            $this->assertNull($context->entries);
        }
    }

    /** @return array<string, array{string, string, array<string, mixed>, ?string, bool}> */
    public static function refusedWrites(): array
    {
        $search = ['search' => []];

        return [
            'mutate' => ['POST', '/written-documents/mutate', self::MUTATE, null, true],
            'destroy' => ['DELETE', '/written-documents', ['resources' => [1, 2]], null, true],
            'restore' => ['POST', '/written-documents/restore', ['resources' => [1, 2]], self::TRASHED, true],
            'force destroy' => ['DELETE', '/written-documents/force', ['resources' => [1, 2]], self::TRASHED, true],
            'action chunk' => ['POST', '/written-documents/actions/archive-documents-by-one', $search, null, true],
            'atomic action' => ['POST', '/written-documents/actions/atomic-archive-documents', $search, null, true],
            'direct action' => ['POST', '/written-documents/actions/direct-archive-documents', $search, null, false],
            'action per row' => ['POST', '/written-documents/actions/archive-each-document', $search, null, true],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('refusedWrites')]
    public function it_answers_a_refusal_and_writes_nothing(string $method, string $uri, array $body, ?string $deletedAt, bool $transacted): void
    {
        config(['rest.extensions' => ['quota' => QuotaGuard::class]]);
        $this->seedDocuments($deletedAt, $deletedAt);
        $events = [ResourceMutated::class, ResourceDestroyed::class, ResourceRestored::class, ResourceForceDestroyed::class, ActionOperated::class];
        Event::fake($events);
        EventLog::listen();

        $this->json($method, $uri, $body)
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertSame([[1, $deletedAt], [2, $deletedAt]], $this->rows());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
        $this->assertSame([], array_values(array_filter(EventLog::$lines, fn (string $line): bool => preg_match('/^sql (insert|update|delete)/', $line) === 1)));
        $this->assertSame($transacted ? ['begin level 2', 'rollback level 1'] : [], $this->transactionLines());

        foreach ($events as $event) {
            Event::assertNotDispatched($event);
        }
    }

    #[Test]
    public function it_refuses_with_403_by_default(): void
    {
        config(['rest.extensions' => ['frozen' => FrozenGuard::class]]);
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'The archive is frozen.']);

        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function guardOrders(): array
    {
        return [
            'frozen first' => [['frozen' => FrozenGuard::class, 'quota' => QuotaGuard::class]],
            'quota first' => [['quota' => QuotaGuard::class, 'frozen' => FrozenGuard::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('guardOrders')]
    public function it_answers_the_refusal_of_the_first_guard_in_name_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'The archive is frozen.']);
    }

    /** @return array<string, array{int}> */
    public static function nonClientStatuses(): array
    {
        return [
            '200' => [200],
            '302' => [302],
            '399' => [399],
            '500' => [500],
        ];
    }

    #[Test]
    #[DataProvider('nonClientStatuses')]
    public function it_refuses_a_status_outside_the_client_errors(int $status): void
    {
        Exceptions::fake();
        WriteLog::$refuseAt = 1;
        WriteLog::$status = $status;
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])->assertInternalServerError();

        $message = 'The [quota] extension '.RecordingGuard::class.' refuses a destroy write on '.WrittenDocumentResource::class." with the status {$status}. Pass a client error status from 400 to 499 to refuse(), such as 403 or 429: a refusal answers the client and rolls the write back.";
        Exceptions::assertReported(fn (InvalidWriteExtensionException $exception): bool => $exception->getMessage() === $message && $exception->namesExtension('quota'));
        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    #[Test]
    public function it_keeps_the_transactions_committed_before_a_refusal(): void
    {
        WriteLog::$refuseAt = 2;
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson('/written-documents/actions/archive-documents-by-one', ['search' => []])
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertSame([
            'operating archive-documents-by-one',
            'begin level 2', 'guard operate 1', 'handle 1', 'sql update documents', 'commit level 1',
            'begin level 2', 'guard operate 2', 'rollback level 1',
        ], $this->writeLines());
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', false]], $this->documents());
    }

    #[Test]
    public function it_guards_each_transaction_of_an_action_whose_mode_an_extension_changes(): void
    {
        config(['rest.extensions' => ['mode' => ModeChangingExtension::class, 'quota' => RecordingGuard::class]]);
        WriteLog::$refuseAt = 2;
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson('/written-documents/actions/archive-documents-by-one', ['search' => []])
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertSame([
            'operating archive-documents-by-one',
            'begin level 2', 'guard operate 1', 'handle 1', 'sql update documents', 'commit level 1',
            'begin level 2', 'guard operate 2', 'rollback level 1',
        ], $this->writeLines());
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', false]], $this->documents());
    }

    #[Test]
    public function it_guards_the_rows_that_the_transaction_mode_of_an_action_handles(): void
    {
        $this->seedDocuments(null, null);
        $request = RestRequest::create('/')->setContainer($this->app);
        $request->setUserResolver(fn (): ArchiveUser => $this->grace);
        $resource = $request->context()->resource(WrittenDocumentResource::class);
        $action = $resource->definition()->action('archive-documents-by-one');
        $fields = ActionFields::of($action, InputValues::fromGiven($resource->definition()->inputFields($action), []), 'web', $this->grace);
        $transaction = WriteTransaction::of($resource);
        EventLog::listen();

        $impacted = ActionTransaction::Whole->aroundRun($transaction, function () use ($action, $fields, $transaction): int {
            EventLog::record('run');
            ActionTransaction::None->handle($action, $fields, $transaction, Document::query()->orderBy('id')->get());

            return 2;
        });
        $direct = ActionTransaction::Chunk->aroundRun($transaction, fn (): int => 0);

        $this->assertSame(2, $impacted);
        $this->assertSame(0, $direct);
        $this->assertSame([
            'begin level 2', 'run', 'guard operate 1,2', 'handle 1,2', 'sql update documents', 'sql update documents', 'commit level 1',
        ], $this->writeLines());
        $this->assertSame(2, WriteLog::$guards[0]->user?->getAuthIdentifier());
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', true]], $this->documents());
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function precognitiveWrites(): array
    {
        return [
            'mutate' => ['POST', '/written-documents/mutate', self::MUTATE],
            'destroy' => ['DELETE', '/written-documents', ['resources' => [1, 2]]],
            'action' => ['POST', '/written-documents/actions/archive-documents-by-one', ['search' => []]],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('precognitiveWrites')]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_never_guards_under_precognition(string $method, string $uri, array $body): void
    {
        $this->seedDocuments(null, null);

        $this->json($method, $uri, $body, ['Precognition' => 'true'])->assertNoContent();

        $this->assertSame([], WriteLog::$guards);
        $this->assertSame([[1, null], [2, null]], $this->rows());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());

        $this->json($method, $uri, $body)->assertOk();

        $this->assertNotSame([], WriteLog::$guards);
    }

    #[Test]
    public function it_writes_nothing_that_a_guard_changes(): void
    {
        config(['rest.extensions' => ['quota' => ChangingGuard::class]]);
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['archived' => true]]]])
            ->assertOk()
            ->assertExactJson(['operations' => [['operation' => 'update', 'key' => 1]]]);
        $destroyed = $this->deleteJson('/written-documents', ['resources' => [2]]);

        $destroyed->assertOk();
        $this->assertSame(['Budget'], array_column($destroyed->json('data'), 'title'));
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', false]], $this->documents());
        $this->assertSame(['Changed', 'Changed'], [WriteLog::$guards[0]->rows[0]->getAttribute('title') ?? null, WriteLog::$guards[1]->rows[0]->getAttribute('title') ?? null]);
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_guards_a_queued_action_in_the_worker(): void
    {
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/queued-archive-documents-by-one', ['search' => []])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([], WriteLog::$guards);
        $this->assertDatabaseCount('jobs', 2);
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertCount(2, WriteLog::$guards);
        $this->assertSame([[1], [2]], array_map(fn ($context): array => array_map(fn (Model $row): mixed => $row->getKey(), $context->rows ?? []), WriteLog::$guards));

        foreach (WriteLog::$guards as $context) {
            $this->assertSame(Endpoint::Operate, $context->endpoint);
            $this->assertInstanceOf(ArchiveUser::class, $context->user);
            $this->assertSame(2, $context->user->getAuthIdentifier());
            $this->assertNotSame($this->grace, $context->user);
        }

        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', true]], $this->documents());
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_fails_the_job_of_a_refused_queued_action(): void
    {
        config(['rest.extensions' => ['quota' => QuotaGuard::class]]);
        $failures = [];
        Event::listen(JobFailed::class, function (JobFailed $event) use (&$failures): void {
            $failures[] = [$event->exception::class, $event->exception->getMessage()];
        });
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/queued-archive-documents-by-one', ['search' => []])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame([[WriteRefused::class, 'The monthly quota is reached.']], $failures);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    /** @return array<string, array{string, string, array<string, mixed>, bool, int}> */
    public static function deniedWrites(): array
    {
        return [
            'mutate of a denied row' => ['POST', '/written-documents/mutate', ['mutate' => [['operation' => 'update', 'key' => 2, 'attributes' => ['title' => 'Budget 2027']]]], false, 403],
            'destroy of a denied row' => ['DELETE', '/written-documents', ['resources' => [1, 2]], false, 403],
            'action on a denied row' => ['POST', '/written-documents/actions/archive-documents-by-one', ['search' => []], false, 403],
            'destroy to confirm' => ['DELETE', '/written-documents', ['resources' => [1]], true, 428],
            'mutate to confirm' => ['POST', '/written-documents/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Annual summary']]]], true, 428],
            'action to confirm' => ['POST', '/written-documents/actions/confirmed-archive-documents', ['search' => ['filters' => [['field' => 'id', 'value' => 1]]]], true, 428],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('deniedWrites')]
    public function it_asks_the_guard_only_after_authorization_and_confirmation(string $method, string $uri, array $body, bool $confirming, int $status): void
    {
        Gate::policy(Document::class, DenyingDocumentPolicy::class);
        WrittenDocumentResource::$confirming = $confirming;
        config(['app.key' => 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=']);
        $this->seedDocuments(null, null);

        $this->json($method, $uri, $body)->assertStatus($status);

        $this->assertSame([], WriteLog::$guards);
        $this->assertSame([[1, null], [2, null]], $this->rows());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    /** @return list<string> */
    private function writeLines(): array
    {
        return array_values(array_filter(EventLog::$lines, fn (string $line): bool => ! str_starts_with($line, 'sql select')));
    }

    /** @return list<string> */
    private function transactionLines(): array
    {
        return array_values(array_filter(EventLog::$lines, fn (string $line): bool => preg_match('/^(begin|commit|rollback) /', $line) === 1));
    }

    /**
     * @param  list<Model>|null  $rows
     * @return list<array{mixed, mixed}>
     */
    private function keysAndTitles(?array $rows): array
    {
        return array_map(fn (Model $row): array => [$row->getKey(), $row->getAttribute('title')], $rows ?? []);
    }

    /** @return list<array{int, string, bool}> */
    private function documents(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [$document->id, $document->title, $document->archived])
            ->all();
    }
}
