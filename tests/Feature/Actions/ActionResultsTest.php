<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TouchDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TouchDocumentResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TouchDocuments;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

class ActionResultsTest extends PackageTestCase
{
    private const array NOTE = ['fields' => [['name' => 'note', 'value' => 'Quarterly']]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        TouchDocuments::reset();
        Rest::resource('touch-documents', TouchDocumentController::class)->register();

        foreach (range(1, 12) as $id) {
            Document::create(['id' => $id, 'title' => "Document {$id}", 'archived' => $id === 12]);
        }
    }

    /**
     * @param  (Closure(ActionFields, int): ?ActionResponse)|null  $answer
     * @param  array<string, mixed>|null  $result
     */
    #[Test]
    #[DataProvider('results')]
    public function it_answers_with_the_result_of_respond(?Closure $answer, ?array $result): void
    {
        TouchDocuments::$answer = $answer;

        $response = $this->postJson('/touch-documents/actions/touch-documents');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 11, 'result' => $result]]);
    }

    /** @return array<string, array{(Closure(ActionFields, int): ?ActionResponse)|null, array<string, mixed>|null}> */
    public static function results(): array
    {
        return [
            'no result' => [null, null],
            'a message' => [fn (ActionFields $fields, int $impacted): ActionResponse => ActionResponse::message("{$impacted} documents touched"), ['type' => 'message', 'message' => '11 documents touched']],
            'a danger message' => [fn (): ActionResponse => ActionResponse::danger('2 documents are locked'), ['type' => 'danger', 'message' => '2 documents are locked']],
            'a download' => [fn (): ActionResponse => ActionResponse::download('https://files.example.com/exports/documents.csv', 'documents.csv'), ['type' => 'download', 'url' => 'https://files.example.com/exports/documents.csv', 'name' => 'documents.csv']],
            'a redirect' => [fn (): ActionResponse => ActionResponse::redirect('https://app.example.com/documents'), ['type' => 'redirect', 'url' => 'https://app.example.com/documents']],
            'created rows' => [fn (): ActionResponse => ActionResponse::created(new Collection([Document::query()->findOrFail(3), Document::query()->findOrFail(1)])), ['type' => 'created', 'keys' => [3, 1]]],
        ];
    }

    #[Test]
    public function it_responds_once_after_every_chunk(): void
    {
        TouchDocuments::$answer = fn (ActionFields $fields, int $impacted): ActionResponse => ActionResponse::message("{$fields->note}: {$impacted}");

        $response = $this->postJson('/touch-documents/actions/touch-documents', self::NOTE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 11, 'result' => ['type' => 'message', 'message' => 'Quarterly: 11']]]);
        $this->assertSame([[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11]], TouchDocuments::$runs);
        $this->assertCount(1, TouchDocuments::$responds);
        $this->assertSame(TouchDocuments::$handled, TouchDocuments::$responds[0]['fields']);
        $this->assertSame([11, 3], [TouchDocuments::$responds[0]['impacted'], TouchDocuments::$responds[0]['runs']]);
    }

    #[Test]
    public function it_responds_to_an_action_without_rows(): void
    {
        TouchDocumentResource::$target = ActionTarget::None;
        TouchDocuments::$answer = fn (ActionFields $fields, int $impacted): ActionResponse => ActionResponse::message("{$impacted} documents touched");

        $response = $this->postJson('/touch-documents/actions/touch-documents');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => ['type' => 'message', 'message' => '0 documents touched']]]);
        $this->assertSame([[]], TouchDocuments::$runs);
    }

    #[Test]
    public function it_responds_after_the_jobs_are_queued(): void
    {
        Queue::fake();
        TouchDocuments::$answer = fn (ActionFields $fields, int $impacted): ActionResponse => ActionResponse::message(Queue::pushed(QueuedAction::class)->count()." jobs for {$impacted} documents");

        $response = $this->postJson('/touch-documents/actions/queued-touch-documents', self::NOTE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 11, 'result' => ['type' => 'message', 'message' => '3 jobs for 11 documents']]]);
        $this->assertSame([], TouchDocuments::$runs);
        $this->assertSame('Quarterly', TouchDocuments::$responds[0]['fields']->note);
    }

    #[Test]
    public function it_responds_after_the_batch_is_dispatched(): void
    {
        Bus::fake();
        TouchDocuments::$answer = fn (ActionFields $fields, int $impacted): ActionResponse => ActionResponse::message(count(Bus::dispatchedBatches())." batch for {$impacted} documents");
        Str::createUuidsUsingSequence([Uuid::fromString('9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f')]);

        $response = $this->postJson('/touch-documents/actions/batched-touch-documents');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 11, 'batch' => '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f', 'result' => ['type' => 'message', 'message' => '1 batch for 11 documents']]]);
    }

    #[Test]
    public function it_gives_the_destructive_flag_and_the_confirmation_of_each_action_in_the_details(): void
    {
        $response = $this->getJson('/touch-documents');

        $response->assertOk();
        $this->assertSame([
            ['name' => 'Touch Documents', 'uriKey' => 'touch-documents', 'target' => 'search', 'fields' => [self::noteEntry()], 'meta' => [], 'destructive' => false, 'confirm' => null],
            ['name' => 'Queued Touch Documents', 'uriKey' => 'queued-touch-documents', 'target' => 'search', 'fields' => [self::noteEntry()], 'meta' => [], 'destructive' => true, 'confirm' => 'Touch every document?'],
            ['name' => 'Batched Touch Documents', 'uriKey' => 'batched-touch-documents', 'target' => 'search', 'fields' => [self::noteEntry()], 'meta' => [], 'destructive' => true, 'confirm' => 'Touch every document in a batch?'],
        ], $response->json('data.actions'));
    }

    /** @return array<string, mixed> */
    private static function noteEntry(): array
    {
        return ['name' => 'note', 'label' => null, 'type' => 'text', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'string'], 'meta' => []];
    }
}
