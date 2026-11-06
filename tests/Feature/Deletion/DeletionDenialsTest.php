<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Exceptions\DenialsRenderer;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\DeletionTrace;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\TracingDocumentController;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\TracingDocumentPolicy;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class DeletionDenialsTest extends PackageTestCase
{
    use SeedsDocuments;

    private const string TRASHED = '2026-01-01 00:00:00';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DeletionTrace::reset();
        Gate::policy(Document::class, TracingDocumentPolicy::class);
        Rest::resource('tracing-documents', TracingDocumentController::class)
            ->withSoftDeletes()
            ->register();
        $this->app->make(ExceptionHandler::class)->renderable(new DenialsRenderer);
    }

    /** @return array<string, array{string, string, ?string, string, string, string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/tracing-documents', null, 'delete', 'beforeDestroy', ' and documents.deleted_at is null'],
            'restore' => ['POST', '/tracing-documents/restore', self::TRASHED, 'restore', 'beforeRestore', ''],
            'force destroy' => ['DELETE', '/tracing-documents/force', self::TRASHED, 'forceDelete', 'beforeForceDestroy', ''],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_checks_every_row_before_refusing(string $method, string $uri, ?string $deletedAt, string $ability, string $before, string $scope): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);
        DeletionTrace::$denials = [2 => 'Budget is locked.', 3 => 'Charter is locked.'];
        DeletionTrace::listen();

        $response = $this->json($method, $uri, ['resources' => [1, 2, 3]]);

        $response->assertForbidden()->assertExactJson([
            'message' => 'Budget is locked.',
            'denials' => [
                ['path' => 'resources.1', 'ability' => $ability, 'key' => 2, 'message' => 'Budget is locked.'],
                ['path' => 'resources.2', 'ability' => $ability, 'key' => 3, 'message' => 'Charter is locked.'],
            ],
        ]);
        $this->assertSame(['sql select * from documents where documents.id in (1, 2, 3)'.$scope, $before, "policy {$ability} 1", "policy {$ability} 2", "policy {$ability} 3"], DeletionTrace::$lines);
        $this->assertSame([[1, $deletedAt], [2, $deletedAt], [3, $deletedAt]], $this->rows());
    }

    #[Test]
    public function it_answers_with_the_first_denied_row_in_the_order_of_the_request(): void
    {
        $this->seedDocuments(null, null, null);
        DeletionTrace::$denials = [2 => 'Budget is locked.', 3 => 'Charter is locked.'];

        $response = $this->deleteJson('/tracing-documents', ['resources' => [3, 1, 2]]);

        $response->assertForbidden()->assertExactJson([
            'message' => 'Charter is locked.',
            'denials' => [
                ['path' => 'resources.0', 'ability' => 'delete', 'key' => 3, 'message' => 'Charter is locked.'],
                ['path' => 'resources.2', 'ability' => 'delete', 'key' => 2, 'message' => 'Budget is locked.'],
            ],
        ]);
        $this->assertSame(['beforeDestroy', 'policy delete 3', 'policy delete 1', 'policy delete 2'], DeletionTrace::$lines);
    }

    #[Test]
    public function it_reports_at_most_fifty_denied_rows(): void
    {
        $this->seedDocuments(...array_fill(0, 51, null));
        DeletionTrace::$denials = array_fill(1, 51, false);

        $response = $this->deleteJson('/tracing-documents', ['resources' => range(1, 51)]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.')
            ->assertJsonCount(50, 'denials')
            ->assertJsonPath('denials.49', ['path' => 'resources.49', 'ability' => 'delete', 'key' => 50, 'message' => 'This action is unauthorized.']);
        $this->assertCount(52, DeletionTrace::$lines);
        $this->assertSame('policy delete 51', DeletionTrace::$lines[51]);
        $this->assertSame(51, Document::query()->count());
    }
}
