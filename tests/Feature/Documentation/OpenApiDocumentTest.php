<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use PHPUnit\Framework\Attributes\Test;

class OpenApiDocumentTest extends DocumentationTestCase
{
    #[Test]
    public function it_generates_an_openapi_3_1_document(): void
    {
        $this->assertSame([], (new OpenApiValidator)->documentErrors($this->document()));
    }

    #[Test]
    public function it_rejects_a_document_whose_schema_breaks_the_openapi_3_1_dialect(): void
    {
        $schema = ['type' => 'object', 'additionalProperties' => [], 'properties' => ['level' => ['type' => 'level']]];
        $document = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Festival', 'version' => '1.0.0'],
            'paths' => ['/shows' => ['get' => ['responses' => ['200' => ['description' => 'Shows', 'content' => ['application/json' => ['schema' => $schema]]]]]]],
        ];

        $this->assertNotSame([], (new OpenApiValidator)->documentErrors($document));
    }
}
