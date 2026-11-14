<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Tests\Fixtures\Festival\ProgramExtension;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['program' => ProgramExtension::class])]
class SnapshotTest extends DocumentationTestCase
{
    private const string SNAPSHOT = __DIR__.'/Snapshots/festival.json';

    #[Test]
    public function it_generates_the_document_of_the_snapshot_fixture(): void
    {
        $document = $this->document();
        unset($document['openapi'], $document['info'], $document['servers']);
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (getenv('REST_REFRESH_SNAPSHOT') === '1') {
            file_put_contents(self::SNAPSHOT, $json);
        }

        $this->assertSame(file_get_contents(self::SNAPSHOT), $json);
    }
}
