<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use PHPUnit\Framework\Attributes\Test;

class InstanceSnapshotTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        ShowResource::$instances = true;

        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void
    {
        ShowResource::$instances = false;

        parent::tearDown();
    }

    #[Test]
    public function it_generates_the_same_document_from_instances_as_from_classes(): void
    {
        $document = $this->document();
        unset($document['openapi'], $document['info'], $document['servers']);

        $this->assertSame(file_get_contents(__DIR__.'/Snapshots/festival.json'), json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }
}
