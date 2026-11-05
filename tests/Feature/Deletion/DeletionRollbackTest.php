<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\ArchivingDocumentController;
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
class DeletionRollbackTest extends PackageTestCase
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
        Rest::resource('tracing-documents', TracingDocumentController::class)->register();
        Rest::resource('archiving-documents', ArchivingDocumentController::class)
            ->withSoftDeletes()
            ->register();
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/archiving-documents', null, 'destroyed 2'],
            'restore' => ['POST', '/archiving-documents/restore', self::TRASHED, 'restored 2'],
            'force destroy' => ['DELETE', '/archiving-documents/force', self::TRASHED, 'forceDestroyed 2'],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_rolls_back_the_writes_of_an_overridden_perform_method(string $method, string $uri, ?string $deletedAt, string $failure): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        DeletionTrace::$failure = $failure;

        $this->json($method, $uri, ['resources' => [1, 2]])->assertInternalServerError();

        $this->assertSame([[1, $deletedAt], [2, $deletedAt]], $this->rows());
        $this->assertDatabaseMissing('documents', ['archived' => true]);
    }

    #[Test]
    public function it_rolls_back_to_its_savepoint_inside_an_open_transaction(): void
    {
        $this->seedDocuments(null, null, null);
        DB::beginTransaction();
        Document::query()
            ->whereKey(3)
            ->update(['title' => 'Charter draft']);
        DeletionTrace::$failure = 'destroyed 2';
        DeletionTrace::listen();

        $this->deleteJson('/tracing-documents', ['resources' => [1, 2]])->assertInternalServerError();

        $this->assertSame(['begin level 3', 'rollback level 2'], array_values(preg_grep('/ level /', DeletionTrace::$lines)));
        $this->assertSame(2, DB::transactionLevel());
        $this->assertSame(['Annual report', 'Budget', 'Charter draft'], Document::query()
            ->orderBy('id')
            ->pluck('title')
            ->all());
        DB::rollBack();
    }
}
