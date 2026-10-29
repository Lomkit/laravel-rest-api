<?php

namespace Lomkit\Rest\Tests\Unit\Http;

use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ControllerResourceTest extends TestCase
{
    #[Test]
    public function it_returns_the_declared_resource_class(): void
    {
        $this->assertSame(DocumentResource::class, DocumentController::resourceClass());
    }

    #[Test]
    public function it_names_the_controller_that_does_not_declare_its_resource(): void
    {
        $controller = new class extends Controller {};

        $this->expectExceptionObject(new InvalidControllerException(
            'The controller ['.$controller::class.'] does not declare its resource. Add public static string $resource = YourResource::class; to it.',
        ));

        $controller::resourceClass();
    }
}
