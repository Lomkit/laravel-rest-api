<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RecordNotes;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TargetNoteController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TargetNoteResource;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionTargetsTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const array REASON = ['name' => 'reason', 'label' => null, 'type' => 'text', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'string'], 'meta' => []];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Workspace];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RecordNotes::$runs = [];
        TargetNoteResource::$declared = null;
        TargetNoteResource::$queued = ActionTarget::Search;
        Rest::resource('target-notes', TargetNoteController::class)->register();
        Note::factory()->create(['id' => 1, 'team' => 'red', 'title' => 'Standup']);
        Note::factory()->create(['id' => 2, 'team' => 'red', 'title' => 'Retro']);
        Note::factory()->create(['id' => 3, 'team' => 'blue', 'title' => 'Roadmap']);
        Note::factory()->create(['id' => 4, 'team' => 'blue', 'title' => 'Public']);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<array{keys: list<int>, reason: mixed}>  $runs
     */
    #[Test]
    #[DataProvider('targets')]
    public function it_hands_each_target_its_rows(?ActionTarget $declared, string $action, array $body, array $runs, int $impacted): void
    {
        TargetNoteResource::$declared = $declared;

        $response = $this->postJson("/target-notes/actions/{$action}", $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame($runs, RecordNotes::$runs);
    }

    /** @return array<string, array{?ActionTarget, string, array<string, mixed>, list<array{keys: list<int>, reason: mixed}>, int}> */
    public static function targets(): array
    {
        return [
            'the search target by default, without a search' => [null, 'record-notes', [], [['keys' => [1, 2, 4], 'reason' => null]], 3],
            'the search target by default, with a search' => [null, 'record-notes', ['search' => ['filters' => [['field' => 'title', 'value' => 'Retro']]]], [['keys' => [2], 'reason' => null]], 1],
            'the resources target as a property' => [null, 'record-named-notes', ['resources' => [1, 4]], [['keys' => [1, 4], 'reason' => null]], 2],
            'the resources target under a perimeter with or' => [null, 'record-named-notes', ['resources' => [1]], [['keys' => [1], 'reason' => null]], 1],
            'the none target as a property' => [null, 'record-no-note', ['fields' => [['name' => 'reason', 'value' => 'Quarterly']]], [['keys' => [], 'reason' => 'Quarterly']], 0],
            'the resources target when declaring' => [ActionTarget::Resources, 'record-notes', ['resources' => [2]], [['keys' => [2], 'reason' => null]], 1],
            'the none target when declaring' => [ActionTarget::None, 'record-notes', [], [['keys' => [], 'reason' => null]], 0],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_body_that_does_not_fit_the_target(string $action, array $body, array $errors): void
    {
        $response = $this->postJson("/target-notes/actions/{$action}", $body);

        $this->assertExactErrors($response, $errors);
        $this->assertSame([], RecordNotes::$runs);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function misfits(): array
    {
        $search = ['filters' => [['field' => 'title', 'value' => 'Retro']]];

        return [
            'resources on the search target' => ['record-notes', ['resources' => [1]], ['resources' => ['The resources field is prohibited.']]],
            'no resources on the resources target' => ['record-named-notes', [], ['resources' => ['The resources field is required.']]],
            'empty resources on the resources target' => ['record-named-notes', ['resources' => []], ['resources' => ['The resources field is required.']]],
            'a search on the resources target' => ['record-named-notes', ['resources' => [1], 'search' => $search], ['search' => ['The search field is prohibited.']]],
            'a key outside the perimeter on the resources target' => ['record-named-notes', ['resources' => [3]], ['resources.0' => ['The selected resources.0 is invalid.']]],
            'a search on the none target' => ['record-no-note', ['search' => $search], ['search' => ['The search field is prohibited.']]],
            'resources on the none target' => ['record-no-note', ['resources' => [1]], ['resources' => ['The resources field is prohibited.']]],
        ];
    }

    #[Test]
    public function it_gives_the_target_of_each_action_in_the_details(): void
    {
        TargetNoteResource::$queued = ActionTarget::None;

        $response = $this->getJson('/target-notes?with=actions.fields,actions.meta,actions.authorized');

        $response->assertOk()->assertJsonPath('data.actions', [
            ['name' => 'Record Notes', 'uriKey' => 'record-notes', 'target' => 'search', 'fields' => [self::REASON], 'meta' => [], 'destructive' => false, 'confirm' => null, 'confirmation' => false, 'authorized' => true],
            ['name' => 'Record Named Notes', 'uriKey' => 'record-named-notes', 'target' => 'resources', 'fields' => [self::REASON], 'meta' => [], 'destructive' => false, 'confirm' => null, 'confirmation' => false, 'authorized' => true],
            ['name' => 'Record No Note', 'uriKey' => 'record-no-note', 'target' => 'none', 'fields' => [self::REASON], 'meta' => [], 'destructive' => false, 'confirm' => null, 'confirmation' => false, 'authorized' => true],
            ['name' => 'Queued Record Notes', 'uriKey' => 'queued-record-notes', 'target' => 'none', 'fields' => [self::REASON], 'meta' => [], 'destructive' => false, 'confirm' => null, 'confirmation' => false, 'authorized' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<int>  $keys
     */
    #[Test]
    #[DataProvider('queuedTargets')]
    public function it_queues_the_rows_of_each_target(ActionTarget $target, array $body, array $keys): void
    {
        TargetNoteResource::$queued = $target;
        Queue::fake();

        $this->postJson('/target-notes/actions/queued-record-notes', $body)->assertOk();
        $job = Queue::pushed(QueuedAction::class)->sole();
        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));
        $this->app->call([$worker, 'handle']);

        $this->assertSame($keys, $job->rows->keys);
        $this->assertSame([['keys' => $keys, 'reason' => null]], RecordNotes::$runs);
    }

    /** @return array<string, array{ActionTarget, array<string, mixed>, list<int>}> */
    public static function queuedTargets(): array
    {
        return [
            'the search target' => [ActionTarget::Search, [], [1, 2, 4]],
            'the resources target' => [ActionTarget::Resources, ['resources' => [1, 4]], [1, 4]],
            'the none target' => [ActionTarget::None, [], []],
        ];
    }
}
