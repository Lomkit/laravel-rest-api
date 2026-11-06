<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Support\Facades\Gate;
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
class DeletionTransactionTest extends PackageTestCase
{
    use SeedsDocuments;

    private const string NOW = '2026-03-01 09:00:00';

    private const string TRASHED = '2026-01-01 00:00:00';

    private const string SOFT_DELETE = 'sql update documents set deleted_at = ?, updated_at = ? where id = ?';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
        DeletionTrace::reset();
        Gate::policy(Document::class, TracingDocumentPolicy::class);
        Rest::resource('tracing-documents', TracingDocumentController::class)
            ->withSoftDeletes()
            ->register();
    }

    /** @return array<string, array{string, string, ?string, list<array{int, ?string}>, list<string>, list<string>, list<string>, string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => [
                'DELETE', '/tracing-documents', null, [[1, self::NOW], [2, self::NOW], [3, self::NOW]],
                ['sql select * from documents where documents.id in (1, 2, 3) and documents.deleted_at is null', 'beforeDestroy', 'policy delete 1', 'policy delete 2', 'policy delete 3'],
                ['destroying #', 'performDelete #', 'model deleting #', self::SOFT_DELETE, 'model deleted #', 'destroyed #'],
                ['committed deleted #'],
                'afterDestroy',
            ],
            'restore' => [
                'POST', '/tracing-documents/restore', self::TRASHED, [[1, null], [2, null], [3, null]],
                ['sql select * from documents where documents.id in (1, 2, 3)', 'beforeRestore', 'policy restore 1', 'policy restore 2', 'policy restore 3'],
                ['restoring #', 'performRestore #', 'model restoring #', self::SOFT_DELETE, 'model restored #', 'restored #'],
                ['committed restored #'],
                'afterRestore',
            ],
            'force destroy' => [
                'DELETE', '/tracing-documents/force', self::TRASHED, [],
                ['sql select * from documents where documents.id in (1, 2, 3)', 'beforeForceDestroy', 'policy forceDelete 1', 'policy forceDelete 2', 'policy forceDelete 3'],
                ['forceDestroying #', 'performForceDelete #', 'model forceDeleting #', 'model deleting #', 'sql delete from documents where id = ?', 'model deleted #', 'model forceDeleted #', 'forceDestroyed #'],
                ['committed deleted #', 'committed forceDeleted #'],
                'afterForceDestroy',
            ],
        ];
    }

    /**
     * @param  list<array{int, ?string}>  $written
     * @param  list<string>  $checks
     * @param  list<string>  $steps
     * @param  list<string>  $committed
     */
    #[Test]
    #[DataProvider('endpoints')]
    public function it_writes_every_row_in_one_transaction(string $method, string $uri, ?string $deletedAt, array $written, array $checks, array $steps, array $committed, string $after): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);
        DeletionTrace::listen();

        $response = $this->json($method, $uri, ['resources' => [1, 2, 3]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1, 2, 3]);
        $this->assertSame([
            ...$checks,
            'begin level 2',
            ...DeletionTrace::forRows($steps, 1, 2, 3),
            ...DeletionTrace::forRows($committed, 1, 2, 3),
            'commit level 1',
            $after,
        ], DeletionTrace::$lines);
        $this->assertSame($written, $this->rows());
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function failures(): array
    {
        return [
            'destroying' => ['DELETE', '/tracing-documents', null, 'destroying 2'],
            'performDelete' => ['DELETE', '/tracing-documents', null, 'performDelete 2'],
            'destroyed' => ['DELETE', '/tracing-documents', null, 'destroyed 2'],
            'restoring' => ['POST', '/tracing-documents/restore', self::TRASHED, 'restoring 2'],
            'performRestore' => ['POST', '/tracing-documents/restore', self::TRASHED, 'performRestore 2'],
            'restored' => ['POST', '/tracing-documents/restore', self::TRASHED, 'restored 2'],
            'forceDestroying' => ['DELETE', '/tracing-documents/force', self::TRASHED, 'forceDestroying 2'],
            'performForceDelete' => ['DELETE', '/tracing-documents/force', self::TRASHED, 'performForceDelete 2'],
            'forceDestroyed' => ['DELETE', '/tracing-documents/force', self::TRASHED, 'forceDestroyed 2'],
        ];
    }

    #[Test]
    #[DataProvider('failures')]
    public function it_leaves_every_row_in_place_when_a_row_fails(string $method, string $uri, ?string $deletedAt, string $failure): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);
        DeletionTrace::$failure = $failure;
        DeletionTrace::listen();

        $response = $this->json($method, $uri, ['resources' => [1, 2, 3]]);

        $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
        $this->assertSame([$failure, 'rollback level 1'], array_slice(DeletionTrace::$lines, -2));
        $this->assertSame([], preg_grep('/^(committed |commit |after)/', DeletionTrace::$lines));
        $this->assertSame([[1, $deletedAt], [2, $deletedAt], [3, $deletedAt]], $this->rows());
    }
}
