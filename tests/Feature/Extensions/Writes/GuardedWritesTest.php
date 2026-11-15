<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Writes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Events\ResourceRestored;
use Lomkit\Rest\Exceptions\InvalidWriteExtensionException;
use Lomkit\Rest\Exceptions\WriteRefused;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveDocumentsByOne;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveEachDocument;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AtomicArchiveDocuments;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DenyingDocumentPolicy;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DirectArchiveDocuments;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FrozenGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FrozenRowGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ModeChangingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaGuardNamedArchive;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WrittenDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
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
    use ResetsExtensionStubs;
    use WritesDocuments;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        WrittenDocumentResource::$confirming = false;
        $this->registerWrittenDocuments(RecordingGuard::class);
    }

    #[\Override]
    protected function tearDown(): void
    {
        WrittenDocumentResource::$confirming = false;

        parent::tearDown();
    }

    #[Test]
    public function it_guards_a_mutation_after_its_checks_and_before_the_first_write(): void
    {
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson('/written-documents/mutate', self::MUTATE)->assertOk()->assertExactJson(self::operations($this->minutes()));

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

    /** @param list<string> $writes */
    #[Test]
    #[DataProvider('rowBatches')]
    public function it_guards_each_endpoint_of_a_row_batch(string $method, string $uri, ?string $deletedAt, Endpoint $endpoint, array $writes): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        EventLog::listen();

        $this->json($method, $uri, ['resources' => [1, 2]])->assertOk()->assertExactJson(self::DOCUMENTS);

        $this->assertSame(['begin level 2', "guard {$endpoint->value} 1,2", ...$writes, 'commit level 1'], $this->writeLines());
        $this->assertCount(1, WriteLog::$guards);
        $this->assertSame($endpoint, WriteLog::$guards[0]->endpoint);
        $this->assertSame(2, WriteLog::$guards[0]->user?->getAuthIdentifier());
        $this->assertSame([[1, 'Annual report'], [2, 'Budget']], $this->keysAndTitles(WriteLog::$guards[0]->rows));
        $this->assertNull(WriteLog::$guards[0]->entries);
    }

    /** @return array<string, array{string, class-string, list<string>, list<list<int>>}> */
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
                'begin level 2',
                'operating atomic-archive-documents',
                'guard operate 1', 'handle 1', 'sql update documents',
                'guard operate 2', 'handle 2', 'sql update documents',
                'operated atomic-archive-documents 2',
                'commit level 1',
            ], [[1], [2]]],
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
     * @param  list<list<int>>  $rows
     */
    #[Test]
    #[DataProvider('actions')]
    public function it_guards_each_transaction_of_an_action(string $uriKey, string $action, array $lines, array $rows): void
    {
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson("/written-documents/actions/{$uriKey}", ['search' => []])
            ->assertOk()
            ->assertExactJson(self::IMPACTED);

        $this->assertSame($lines, $this->writeLines());
        $this->assertSame($rows, array_map(fn ($context): array => array_map(fn (Model $row): mixed => $row->getKey(), $context->rows), WriteLog::$guards));

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
        config(['rest.extensions' => [QuotaGuard::class]]);
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
        config(['rest.extensions' => [FrozenGuard::class]]);
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'The archive is frozen.']);

        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    /** @return array<string, array{list<class-string>, int, string}> */
    public static function guardOrders(): array
    {
        return [
            'frozen first' => [[FrozenGuard::class, QuotaGuard::class], 403, 'The archive is frozen.'],
            'quota first' => [[QuotaGuard::class, FrozenGuard::class], 403, 'The archive is frozen.'],
            'quota named archive, configured last' => [[FrozenGuard::class, QuotaGuardNamedArchive::class], 429, 'The monthly quota is reached.'],
            'quota named archive, configured first' => [[QuotaGuardNamedArchive::class, FrozenGuard::class], 429, 'The monthly quota is reached.'],
        ];
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('guardOrders')]
    public function it_answers_the_refusal_of_the_first_guard_in_name_order(array $extensions, int $status, string $message): void
    {
        config(['rest.extensions' => $extensions]);
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])
            ->assertStatus($status)
            ->assertExactJson(['message' => $message]);
    }

    /** @return array<string, array{int}> */
    public static function nonClientStatuses(): array
    {
        return [
            '399' => [399],
            '500' => [500],
            '504' => [504],
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

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);

        $message = 'The [quota] extension '.RecordingGuard::class.' refuses a destroy write on '.WrittenDocumentResource::class." with the status {$status}. Pass 403, another client error status from 400 to 499 such as 423 or 429, or 503 to refuse(): a refusal answers the client and rolls the write back.";
        Exceptions::assertReported(fn (InvalidWriteExtensionException $exception): bool => $exception->getMessage() === $message && $exception->concerns(RecordingGuard::class) && ! $exception->concerns('quota'));
        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    /** @return array<string, array{int, bool}> */
    public static function reservedStatuses(): array
    {
        return [
            '409' => [409, false],
            '409 from a guard bound to a subclass' => [409, true],
            '422' => [422, false],
            '428' => [428, false],
        ];
    }

    #[Test]
    #[DataProvider('reservedStatuses')]
    public function it_refuses_a_status_that_the_package_answers_with_another_body(int $status, bool $bindsSubclass): void
    {
        Exceptions::fake();
        $bound = $bindsSubclass ? new class extends RecordingGuard {} : null;

        if ($bound !== null) {
            $this->app->bind(RecordingGuard::class, static fn (): RecordingGuard => $bound);
        }

        WriteLog::$refuseAt = 1;
        WriteLog::$status = $status;
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);

        $message = 'The [quota] extension '.RecordingGuard::class.' refuses a destroy write on '.WrittenDocumentResource::class." with the status {$status}, which the package answers with another body (409 conflicts, 422 validation errors, 428 confirmations). Refuse with 423 or 429.";
        Exceptions::assertReported(fn (InvalidWriteExtensionException $exception): bool => $exception->getMessage() === $message
            && $exception->concerns(RecordingGuard::class)
            && ! $exception->concerns('quota')
            && ($bound === null || ! $exception->concerns($bound::class)));
        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    /** @return array<string, array{int, string, string}> */
    public static function refusalsWithHeaders(): array
    {
        return [
            'maintenance' => [503, 'The archive is under maintenance.', '600'],
            'quota' => [429, 'The monthly quota is reached.', '60'],
        ];
    }

    #[Test]
    #[DataProvider('refusalsWithHeaders')]
    public function it_answers_a_refusal_with_its_status_and_headers(int $status, string $refusal, string $retryAfter): void
    {
        WriteLog::$refuseAt = 1;
        WriteLog::$status = $status;
        WriteLog::$refusal = $refusal;
        WriteLog::$headers = ['Retry-After' => $retryAfter];
        $this->seedDocuments(null, null);

        $this->deleteJson('/written-documents', ['resources' => [1, 2]])
            ->assertStatus($status)
            ->assertHeader('Retry-After', $retryAfter)
            ->assertExactJson(['message' => $refusal]);

        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    /** @return array<string, array{string, list<string>}> */
    public static function refusedLaterWrites(): array
    {
        return [
            'chunk' => ['archive-documents-by-one', [
                'operating archive-documents-by-one',
                'begin level 2', 'guard operate 1', 'handle 1', 'sql update documents', 'commit level 1',
                'begin level 2', 'guard operate 2', 'rollback level 1',
            ]],
            'without transaction' => ['direct-archive-documents', [
                'operating direct-archive-documents',
                'guard operate 1', 'handle 1', 'sql update documents',
                'guard operate 2',
            ]],
            'per row' => ['archive-each-document', [
                'operating archive-each-document',
                'before 1', 'begin level 2', 'guard operate 1', 'row 1', 'sql update documents', 'commit level 1',
                'before 2', 'begin level 2', 'guard operate 2', 'rollback level 1',
            ]],
        ];
    }

    /** @param list<string> $lines */
    #[Test]
    #[DataProvider('refusedLaterWrites')]
    public function it_keeps_the_writes_committed_before_a_refusal(string $uriKey, array $lines): void
    {
        WriteLog::$refuseAt = 2;
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson("/written-documents/actions/{$uriKey}", ['search' => []])
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertSame($lines, $this->writeLines());
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', false]], $this->documents());
    }

    #[Test]
    public function it_refuses_a_whole_action_on_a_frozen_row_and_writes_nothing(): void
    {
        config(['rest.extensions' => [FrozenRowGuard::class]]);
        WriteLog::$frozen = [2];
        $this->seedDocuments(null, null);
        Event::fake([ActionOperated::class]);
        EventLog::listen();

        $this->postJson('/written-documents/actions/atomic-archive-documents', ['search' => []])
            ->assertStatus(423)
            ->assertExactJson(['message' => 'The document is frozen.']);

        $this->assertSame([
            'begin level 2', 'operating atomic-archive-documents', 'handle 1', 'sql update documents', 'rollback level 1',
        ], $this->writeLines());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
        Event::assertNotDispatched(ActionOperated::class);
    }

    #[Test]
    public function it_guards_each_transaction_of_an_action_whose_mode_an_extension_changes(): void
    {
        config(['rest.extensions' => [ModeChangingExtension::class, RecordingGuard::class]]);
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

    /** @return array<string, array{string, string, array<string, mixed>, Closure(?int): array<string, mixed>}> */
    public static function precognitiveWrites(): array
    {
        return [
            'mutate' => ['POST', '/written-documents/mutate', self::MUTATE, fn (int $minutes): array => self::operations($minutes)],
            'destroy' => ['DELETE', '/written-documents', ['resources' => [1, 2]], fn (): array => self::DOCUMENTS],
            'action' => ['POST', '/written-documents/actions/archive-documents-by-one', ['search' => []], fn (): array => self::IMPACTED],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  Closure(?int): array<string, mixed>  $answer
     */
    #[Test]
    #[DataProvider('precognitiveWrites')]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_never_guards_under_precognition(string $method, string $uri, array $body, Closure $answer): void
    {
        $this->seedDocuments(null, null);

        $this->json($method, $uri, $body, ['Precognition' => 'true'])->assertNoContent();

        $this->assertSame([], WriteLog::$guards);
        $this->assertSame([[1, null], [2, null]], $this->rows());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());

        $this->json($method, $uri, $body)->assertOk()->assertExactJson($answer($this->minutes()));

        $this->assertNotSame([], WriteLog::$guards);
    }

    #[Test]
    public function it_writes_nothing_that_a_guard_changes(): void
    {
        config(['rest.extensions' => [ChangingGuard::class]]);
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['archived' => true]]]])
            ->assertOk()
            ->assertExactJson(['operations' => [['operation' => 'update', 'key' => 1]]]);
        $destroyed = $this->deleteJson('/written-documents', ['resources' => [2]]);

        $destroyed->assertOk()->assertExactJson(['data' => [['id' => 2, 'title' => 'Budget', 'archived' => false]], 'meta' => []]);
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', false]], $this->documents());
        $this->assertSame(['Changed', 'Changed'], [WriteLog::$guards[0]->rows[0]->getAttribute('title') ?? null, WriteLog::$guards[1]->rows[0]->getAttribute('title') ?? null]);
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_guards_a_queued_action_in_the_request_and_in_the_worker(): void
    {
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/queued-archive-documents-by-one', ['search' => []])
            ->assertOk()
            ->assertExactJson(self::IMPACTED);
        $this->assertCount(2, WriteLog::$guards);
        $this->assertSame([$this->grace, $this->grace], array_map(fn ($context): mixed => $context->user, WriteLog::$guards));
        $this->assertDatabaseCount('jobs', 2);
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertCount(4, WriteLog::$guards);
        $this->assertSame([[1], [2], [1], [2]], array_map(fn ($context): array => array_map(fn (Model $row): mixed => $row->getKey(), $context->rows), WriteLog::$guards));

        foreach (WriteLog::$guards as $context) {
            $this->assertSame(Endpoint::Operate, $context->endpoint);
            $this->assertInstanceOf(ArchiveUser::class, $context->user);
            $this->assertSame(2, $context->user->getAuthIdentifier());
        }

        $this->assertNotSame($this->grace, WriteLog::$guards[2]->user);
        $this->assertNotSame($this->grace, WriteLog::$guards[3]->user);
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', true]], $this->documents());
    }

    /** @return array<string, array{int, list<list<int>>}> */
    public static function queuedRefusals(): array
    {
        return [
            'the first chunk' => [1, [[1]]],
            'a later chunk' => [2, [[1], [2]]],
        ];
    }

    /** @param list<list<int>> $guarded */
    #[Test]
    #[DataProvider('queuedRefusals')]
    #[WithConfig('queue.default', 'database')]
    public function it_queues_no_job_when_a_chunk_is_refused_in_the_request(int $refuseAt, array $guarded): void
    {
        WriteLog::$refuseAt = $refuseAt;
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/queued-archive-documents-by-one', ['search' => []])
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame($guarded, $this->guardedKeys());
        $this->assertSame([[1, 'Annual report']], $this->keysAndTitles(WriteLog::$guards[0]->rows));
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());

        foreach (WriteLog::$guards as $context) {
            $this->assertSame($this->grace, $context->user);
        }
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_guards_each_chunk_of_a_batched_action_in_the_request_and_in_the_worker(): void
    {
        config(['queue.batching.database' => config('database.default')]);
        $this->seedDocuments(null, null);

        $response = $this->postJson('/written-documents/actions/batched-archive-documents-by-one', ['search' => []])->assertOk();

        $batch = DB::table('job_batches')->value('id');
        $response->assertExactJson(['data' => ['impacted' => 2, 'result' => null, 'batch' => $batch]]);
        $this->assertSame([[1], [2]], $this->guardedKeys());
        $this->assertSame([$this->grace, $this->grace], array_map(fn ($context): mixed => $context->user, WriteLog::$guards));
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame([[1], [2], [1], [2]], $this->guardedKeys());
        $this->assertNotSame($this->grace, WriteLog::$guards[2]->user);
        $this->assertSame(2, WriteLog::$guards[3]->user?->getAuthIdentifier());
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', true]], $this->documents());
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_queues_no_batch_when_the_request_guard_refuses(): void
    {
        config(['queue.batching.database' => config('database.default')]);
        WriteLog::$refuseAt = 2;
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/batched-archive-documents-by-one', ['search' => []])
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertSame([[1], [2]], $this->guardedKeys());
        $this->assertDatabaseCount('job_batches', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    #[Test]
    #[WithConfig('queue.default', 'deferred')]
    public function it_fails_a_job_refused_after_the_response(): void
    {
        Exceptions::fake();
        WriteLog::$refuseAt = 3;
        $failures = [];
        Event::listen(JobFailed::class, function (JobFailed $event) use (&$failures): void {
            $failures[] = [$event->connectionName, $event->exception::class, $event->exception->getMessage()];
        });
        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) use (&$failures): void {
            $failures[] = ['exception', $event->exception::class];
        });
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/queued-archive-documents-by-one', ['search' => []])
            ->assertOk()
            ->assertExactJson(self::IMPACTED);

        $this->assertSame([['deferred', WriteRefused::class, 'The [quota] extension refused the write.']], $failures);
        Exceptions::assertNothingReported();
        $this->assertCount(4, WriteLog::$guards);
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', true]], $this->documents());
    }

    /** @return array<string, array{string}> */
    public static function queuedActions(): array
    {
        return [
            'one try' => ['queued-archive-documents-by-one'],
            'three tries' => ['retried-queued-archive-documents'],
        ];
    }

    #[Test]
    #[DataProvider('queuedActions')]
    #[WithConfig('queue.default', 'database')]
    public function it_fails_a_refused_job_without_a_retry(string $uriKey): void
    {
        config(['rest.extensions' => [FrozenRowGuard::class], 'queue.failed.database' => config('database.default')]);
        $failures = [];
        Event::listen(JobFailed::class, function (JobFailed $event) use (&$failures): void {
            $failures[] = [$event->exception::class, $event->exception->getMessage(), $event->exception instanceof WriteRefused ? $event->exception->reason : null];
        });
        $this->seedDocuments(null, null);

        $this->postJson("/written-documents/actions/{$uriKey}", ['search' => []])->assertOk()->assertExactJson(self::IMPACTED);
        WriteLog::$frozen = [1];
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame([[WriteRefused::class, 'The [frozen] extension refused the write.', 'The document is frozen.']], $failures);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    /** @return array<string, array{string, string, array<string, mixed>, bool, int, string}> */
    public static function deniedWrites(): array
    {
        $denied = 'This action is unauthorized.';

        return [
            'mutate of a denied row' => ['POST', '/written-documents/mutate', ['mutate' => [['operation' => 'update', 'key' => 2, 'attributes' => ['title' => 'Budget 2027']]]], false, 403, $denied],
            'destroy of a denied row' => ['DELETE', '/written-documents', ['resources' => [1, 2]], false, 403, $denied],
            'action on a denied row' => ['POST', '/written-documents/actions/archive-documents-by-one', ['search' => []], false, 403, $denied],
            'destroy to confirm' => ['DELETE', '/written-documents', ['resources' => [1]], true, 428, 'Delete the documents?'],
            'mutate to confirm' => ['POST', '/written-documents/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Annual summary']]]], true, 428, 'Change the documents?'],
            'action to confirm' => ['POST', '/written-documents/actions/confirmed-archive-documents', ['search' => ['filters' => [['field' => 'id', 'value' => 1]]]], true, 428, 'Archive the documents?'],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('deniedWrites')]
    #[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
    public function it_asks_the_guard_only_after_authorization_and_confirmation(string $method, string $uri, array $body, bool $confirming, int $status, string $message): void
    {
        Gate::policy(Document::class, DenyingDocumentPolicy::class);
        WrittenDocumentResource::$confirming = $confirming;
        $this->seedDocuments(null, null);

        $response = $this->json($method, $uri, $body)->assertStatus($status)->assertJsonPath('message', $message);

        $this->assertSame($confirming ? ['message', 'confirmation'] : ['message'], array_keys($response->json()));

        $this->assertSame([], WriteLog::$guards);
        $this->assertSame([[1, null], [2, null]], $this->rows());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    /** @return list<list<mixed>> */
    private function guardedKeys(): array
    {
        return array_map(fn ($context): array => array_map(fn (Model $row): mixed => $row->getKey(), $context->rows), WriteLog::$guards);
    }

    /** @return list<string> */
    private function transactionLines(): array
    {
        return array_values(array_filter(EventLog::$lines, fn (string $line): bool => preg_match('/^(begin|commit|rollback) /', $line) === 1));
    }

    /**
     * @param  list<Model>  $rows
     * @return list<array{mixed, mixed}>
     */
    private function keysAndTitles(array $rows): array
    {
        return array_map(fn (Model $row): array => [$row->getKey(), $row->getAttribute('title')], $rows);
    }
}
