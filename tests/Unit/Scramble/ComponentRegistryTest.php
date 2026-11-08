<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
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

    #[Test]
    public function it_builds_a_schema_component_once_for_its_owner(): void
    {
        $components = new Components;
        $registry = new ComponentRegistry($components);
        $builds = 0;
        $build = function () use ($registry, &$builds): Type {
            $builds++;

            return (new ObjectType)->addProperty('parent', $registry->schema('Stage', 'App\Rest\StageResource', fn (): Type => new StringType));
        };

        $first = $registry->schema('Stage', 'App\Rest\StageResource', $build);
        $second = $registry->schema('Stage', 'App\Rest\StageResource', $build);

        $this->assertSame(1, $builds);
        $this->assertSame(['Stage'], array_keys($components->schemas));
        $this->assertSame('{"type":"object","properties":{"parent":{"$ref":"#\/components\/schemas\/Stage"}}}', json_encode($components->schemas['Stage']->type->serializeAs31()));
        $this->assertSame('#/components/schemas/Stage', $first->getUri());
        $this->assertSame('#/components/schemas/Stage', $second->getUri());
    }

    #[Test]
    public function it_refuses_a_schema_name_that_another_owner_took(): void
    {
        $registry = new ComponentRegistry(new Components);
        $registry->schema('Stage', 'App\Rest\StageResource', fn (): Type => new ObjectType);

        $this->expectExceptionObject(new InvalidDocumentationException(
            'The OpenAPI schema component [Stage] of [App\Rest\Admin\StageResource] is already defined differently by [App\Rest\StageResource]. Give one of them another name.',
        ));

        $registry->schema('Stage', 'App\Rest\Admin\StageResource', fn (): Type => new ObjectType);
    }

    #[Test]
    public function it_takes_the_fallback_name_when_another_owner_took_the_name(): void
    {
        $components = new Components;
        $registry = new ComponentRegistry($components);
        $registry->schema('Stage', 'App\Rest\StageResource', fn (): Type => new ObjectType, 'App.Rest.Stage');

        $other = $registry->schema('Stage', 'App\Rest\Admin\StageResource', fn (): Type => new StringType, 'App.Rest.Admin.Stage');
        $again = $registry->schema('Stage', 'App\Rest\Admin\StageResource', fn (): Type => new StringType, 'App.Rest.Admin.Stage');

        $this->assertSame(['Stage', 'App.Rest.Admin.Stage'], array_keys($components->schemas));
        $this->assertSame('#/components/schemas/App.Rest.Admin.Stage', $other->getUri());
        $this->assertSame('#/components/schemas/App.Rest.Admin.Stage', $again->getUri());
    }
}
