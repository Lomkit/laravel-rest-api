<?php

namespace Lomkit\Rest\Tests\Unit\Http;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceModelTest extends TestCase
{
    #[Test]
    public function it_returns_the_declared_model_class(): void
    {
        $this->assertSame(Document::class, DocumentResource::modelClass());
    }

    #[Test]
    public function it_names_the_resource_that_does_not_declare_its_model(): void
    {
        $resource = new class extends Resource {};

        $this->expectExceptionObject($this->missingModel($resource));

        $resource::modelClass();
    }

    #[Test]
    public function it_names_the_resource_without_a_model_when_it_builds_one(): void
    {
        $resource = new class extends Resource {};

        $this->expectExceptionObject($this->missingModel($resource));

        $resource::newModel();
    }

    private function missingModel(Resource $resource): InvalidResourceException
    {
        return new InvalidResourceException(
            'The resource ['.$resource::class.'] does not declare its model. Add public static string $model = YourModel::class; to it.',
        );
    }
}
