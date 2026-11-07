<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Response;
use Lomkit\Rest\Exceptions\InvalidDocumentationException;
use Lomkit\Rest\Scramble\ComponentRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ComponentRegistryTest extends TestCase
{
    #[Test]
    public function it_gives_the_same_component_one_entry(): void
    {
        $components = new Components;
        $registry = new ComponentRegistry($components);

        $first = $registry->response('RestNotFound', Response::make(404)->setDescription('Not found'), 'App\Docs\First');
        $second = $registry->response('RestNotFound', Response::make(404)->setDescription('Not found'), 'App\Docs\Second');

        $this->assertSame(['RestNotFound'], array_keys($components->responses));
        $this->assertSame('#/components/responses/RestNotFound', $first->getUri());
        $this->assertSame('#/components/responses/RestNotFound', $second->getUri());
    }

    #[Test]
    public function it_refuses_another_component_under_a_taken_name(): void
    {
        $registry = new ComponentRegistry(new Components);
        $registry->response('RestNotFound', Response::make(404)->setDescription('Not found'), 'App\Docs\First');

        $this->expectExceptionObject(new InvalidDocumentationException(
            'The OpenAPI response component [RestNotFound] of [App\Docs\Second] is already defined differently by [App\Docs\First]. Give one of them another name.',
        ));

        $registry->response('RestNotFound', Response::make(404)->setDescription('Missing'), 'App\Docs\Second');
    }
}
