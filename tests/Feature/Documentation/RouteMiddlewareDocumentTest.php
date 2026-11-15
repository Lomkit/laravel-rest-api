<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuardRoutes;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [GuardRoutes::class])]
class RouteMiddlewareDocumentTest extends DocumentationTestCase
{
    #[Test]
    public function it_documents_the_authentication_that_an_extension_adds(): void
    {
        $document = $this->document();

        $this->assertSame(['auth'], array_slice($this->app['router']->getRoutes()->getByName('stages.search')?->middleware() ?? [], -1));
        $this->assertArrayHasKey('401', $document['paths']['/stages/search']['post']['responses']);
        $this->assertArrayNotHasKey('401', $document['paths']['/notes/search']['post']['responses']);
    }
}
