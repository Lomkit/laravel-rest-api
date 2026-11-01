<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\RecordingDocumentController;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\RecordingDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class HookResourceTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RecordingDocumentResource::$hooked = [];
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/recording-documents', null],
            'restore' => ['POST', '/recording-documents/restore', '2026-01-01 00:00:00'],
            'force destroy' => ['DELETE', '/recording-documents/force', '2026-01-01 00:00:00'],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_gives_the_query_hook_the_resource_while_checking_the_keys(string $method, string $uri, ?string $deletedAt): void
    {
        Rest::resource('recording-documents', RecordingDocumentController::class)
            ->withSoftDeletes()
            ->register();
        Document::factory()->create(['id' => 1, 'title' => 'Annual report', 'deleted_at' => $deletedAt]);

        $this->json($method, $uri, ['resources' => [1]])->assertOk();

        $this->assertSame([RecordingDocumentResource::class, RecordingDocumentResource::class], RecordingDocumentResource::$hooked);
    }
}
