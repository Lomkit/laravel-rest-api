<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\DeletionTrace;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\TracingDocumentPolicy;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class DeletedRowsTest extends PackageTestCase
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
    }

    /** @return array<string, array{string, string, list<?string>, list<array{int, ?string}>}> */
    public static function writes(): array
    {
        return [
            'destroy' => ['DELETE', '/api/documents', [null, null], [[1, self::TRASHED], [2, self::TRASHED]]],
            'restore' => ['POST', '/api/documents/restore', [self::TRASHED, self::TRASHED], [[1, null], [2, null]]],
            'force destroy a live and a trashed row' => ['DELETE', '/api/documents/force', [null, self::TRASHED], []],
        ];
    }

    /**
     * @param  list<?string>  $deletedAt
     * @param  list<array{int, ?string}>  $rows
     */
    #[Test]
    #[DataProvider('writes')]
    public function it_writes_every_row(string $method, string $uri, array $deletedAt, array $rows): void
    {
        $this->travelTo(self::TRASHED);
        $this->seedDocuments(...$deletedAt);

        $response = $this->json($method, $uri, ['resources' => [1, 2]]);

        $response->assertOk()->assertExactJson(['data' => [
            ['id' => 1, 'title' => 'Annual report', 'archived' => false],
            ['id' => 2, 'title' => 'Budget', 'archived' => false],
        ], 'meta' => []]);
        $this->assertSame($rows, $this->rows());
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/api/documents', null, 'delete'],
            'restore' => ['POST', '/api/documents/restore', self::TRASHED, 'restore'],
            'force destroy' => ['DELETE', '/api/documents/force', self::TRASHED, 'forceDelete'],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_keeps_every_row_when_the_policy_denies_one(string $method, string $uri, ?string $deletedAt, string $ability): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        DeletionTrace::$denials = [1 => false];

        $response = $this->json($method, $uri, ['resources' => [1, 2]]);

        $response->assertForbidden()->assertExactJson(['message' => 'This action is unauthorized.']);
        $this->assertSame("policy {$ability} 1", DeletionTrace::$lines[0]);
        $this->assertSame([[1, $deletedAt], [2, $deletedAt]], $this->rows());
    }
}
