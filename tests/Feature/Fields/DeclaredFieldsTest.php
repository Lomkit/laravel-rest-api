<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Listing;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DeclaredFieldsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_builds_the_field_set_once_per_request(): void
    {
        $resource = $this->context()->resource(ProductResource::class);
        $request = $resource->context()->request;

        $this->assertSame($resource->getFields($request), $resource->getFields($request));
        $this->assertNotSame($resource->getFields($request), $this->context()->resource(ProductResource::class)->getFields($request));
    }

    #[Test]
    public function it_binds_the_fields_to_the_resource_model(): void
    {
        $resource = new class extends Resource
        {
            public static string $model = Post::class;

            #[\Override]
            public function fields(RestRequest $request): array
            {
                return [ID::make('id'), Enum::make('status')];
            }
        };

        $this->assertSame(PostStatus::class, $resource->getFields($resource->context()->request)->get('status')->enumClass());
    }

    #[Test]
    public function it_leaves_the_fields_unbound_when_the_model_is_abstract(): void
    {
        $resource = new class extends Resource
        {
            public static string $model = Listing::class;

            #[\Override]
            public function fields(RestRequest $request): array
            {
                return [ID::make('id'), Text::make('name')];
            }
        };

        $this->assertSame(['id', 'name'], $resource->getFields($resource->context()->request)->names());
    }

    #[Test]
    public function it_names_the_resource_when_fields_returns_a_string(): void
    {
        $resource = new class extends Resource
        {
            public static string $model = Product::class;

            #[\Override]
            public function fields(RestRequest $request): array
            {
                return [ID::make('id'), 'name'];
            }
        };

        $this->expectExceptionObject(new InvalidFieldException(
            $resource::class."::fields() returned a string at index 1 ('name'). Return Field objects, for example Text::make('name').",
        ));

        $resource->getFields($resource->context()->request);
    }

    #[Test]
    public function it_names_the_resource_when_fields_declares_a_name_twice(): void
    {
        $resource = new class extends Resource
        {
            public static string $model = Product::class;

            #[\Override]
            public function fields(RestRequest $request): array
            {
                return [ID::make('id'), Text::make('name'), Text::make('name')];
            }
        };

        $this->expectExceptionObject(new InvalidFieldException($resource::class.'::fields() declares the field [name] twice.'));

        $resource->getFields($resource->context()->request);
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app);
    }
}
