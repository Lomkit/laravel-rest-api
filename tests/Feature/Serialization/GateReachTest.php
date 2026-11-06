<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\LockedDocumentController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\LockedDocumentResource;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\TrashedDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class GateReachTest extends PackageTestCase
{
    use CapturesQueries;

    private const array WRITE = ['update', 'delete', 'restore', 'forceDelete'];

    private const array SELECTS = [['field' => 'id']];

    /** @var list<string> */
    private array $calls = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('trashed-documents', TrashedDocumentController::class)
            ->withSoftDeletes()
            ->register();
        Rest::resource('locked-documents', LockedDocumentController::class)
            ->withSoftDeletes()
            ->register();
        LockedDocumentResource::$endpoints = [];
        Document::query()->insert([
            ['id' => 1, 'title' => 'Draft', 'archived' => false, 'deleted_at' => null],
            ['id' => 2, 'title' => 'Report', 'archived' => false, 'deleted_at' => '2026-01-01 00:00:00'],
            ['id' => 3, 'title' => 'Locked', 'archived' => false, 'deleted_at' => null],
            ['id' => 4, 'title' => 'Archived', 'archived' => true, 'deleted_at' => null],
        ]);
        Gate::before(function (?Authenticatable $user, string $ability, array $arguments): ?bool {
            $subject = $arguments[0] ?? null;
            $this->calls[] = $subject instanceof Model ? "{$ability} {$subject->getKey()}" : $ability;

            return $ability === 'update' && $subject instanceof Model && (int) $subject->getKey() === 3 ? false : null;
        });
    }

    #[Test]
    public function it_answers_the_write_gates_of_rows_read_through_the_perimeter_with_the_policy(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/current-documents/search', ['search' => ['gates' => self::WRITE, 'selects' => self::SELECTS]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 1]);
        $this->assertAllowed($response, 'data', [
            'update' => [false, true],
            'delete' => [true, true],
            'restore' => [true, true],
            'force_delete' => [true, true],
        ]);
        $this->assertCount(1, $this->readsOf('documents'));
    }

    #[Test]
    public function it_answers_false_to_update_and_delete_on_a_trashed_row_that_the_perimeter_leaves_out_of_writes(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/current-documents/search', ['search' => ['trashed' => 'with', 'gates' => self::WRITE, 'selects' => self::SELECTS]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [3, 2, 1])
            ->assertJsonPath('data.1.gates.authorized_to_update', ['allowed' => false, 'message' => null]);
        $this->assertAllowed($response, 'data', [
            'update' => [false, false, true],
            'delete' => [true, false, true],
            'restore' => [true, true, true],
            'force_delete' => [true, true, true],
        ]);
        $this->assertSame(
            [['select documents.id from documents where (archived = ?) and documents.id in (3, 2, 1) and documents.deleted_at is null', [false]]],
            array_slice($this->readsOf('documents'), 1),
        );
        $this->assertSame(['viewAny', 'update 3', 'delete 3', 'restore 3', 'forceDelete 3', 'restore 2', 'forceDelete 2', 'update 1', 'delete 1', 'restore 1', 'forceDelete 1'], $this->calls);
    }

    #[Test]
    public function it_answers_the_policy_on_a_trashed_row_that_the_perimeter_keeps_for_writes(): void
    {
        $response = $this->postJson('/trashed-documents/search', ['search' => ['trashed' => 'with', 'gates' => self::WRITE, 'selects' => self::SELECTS]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 2, 1]);
        $this->assertAllowed($response, 'data', [
            'update' => [false, true, true],
            'delete' => [true, true, true],
            'restore' => [true, true, true],
            'force_delete' => [true, true, true],
        ]);
    }

    #[Test]
    public function it_answers_the_write_gates_of_a_mutate_record_with_the_policy(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/current-documents/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Final']]],
            'response' => ['gates' => self::WRITE, 'selects' => self::SELECTS],
        ]);

        $response->assertOk()->assertJsonPath('records.*.id', [1]);
        $this->assertAllowed($response, 'records', [
            'update' => [true],
            'delete' => [true],
            'restore' => [true],
            'force_delete' => [true],
        ]);
        $this->assertCount(2, $this->readsOf('documents'));
    }

    #[Test]
    public function it_reads_the_perimeter_of_a_gate_with_the_request_that_renders_it(): void
    {
        $search = $this->postJson('/locked-documents/search', ['search' => ['gates' => ['delete'], 'selects' => self::SELECTS]]);
        $endpoints = LockedDocumentResource::$endpoints;
        $destroy = $this->deleteJson('/locked-documents', ['resources' => [3]]);

        $search->assertOk()
            ->assertJsonPath('data.*.id', [3, 1])
            ->assertJsonPath('data.*.gates.authorized_to_delete.allowed', [true, true]);
        $this->assertSame(['search'], $endpoints);
        $destroy->assertUnprocessable()->assertJsonPath('errors', ['resources.0' => ['The selected resources.0 is invalid.']]);
    }

    /** @param array<string, list<bool>> $allowed */
    private function assertAllowed(TestResponse $response, string $rows, array $allowed): void
    {
        foreach ($allowed as $gate => $answers) {
            $response->assertJsonPath("{$rows}.*.gates.authorized_to_{$gate}.allowed", $answers);
        }
    }
}
