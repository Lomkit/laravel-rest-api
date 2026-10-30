<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class RelationConstraintsTest extends PackageTestCase
{
    public static function constraints(): iterable
    {
        yield 'required on creation' => ['requiredOnCreation', 'isRequiredOnCreation'];
        yield 'prohibited on creation' => ['prohibitedOnCreation', 'isProhibitedOnCreation'];
        yield 'required on update' => ['requiredOnUpdate', 'isRequiredOnUpdate'];
        yield 'prohibited on update' => ['prohibitedOnUpdate', 'isProhibitedOnUpdate'];
    }

    #[Test]
    #[DataProvider('constraints')]
    public function it_evaluates_a_constraint_callback_for_each_request(string $declare, string $evaluate): void
    {
        $relation = BelongsTo::make('category', CategoryResource::class)
            ->fromResource(new ProductResource)
            ->{$declare}(fn (RestRequest $request): bool => $request->boolean('strict'));

        $this->assertTrue($relation->{$evaluate}(new RestRequest(['strict' => '1'])));
        $this->assertFalse($relation->{$evaluate}(new RestRequest(['strict' => '0'])));
    }

    #[Test]
    #[DataProvider('constraints')]
    public function it_passes_the_request_and_the_related_resource_to_a_constraint_callback(string $declare, string $evaluate): void
    {
        $relation = BelongsTo::make('category', CategoryResource::class)
            ->fromResource(new ProductResource)
            ->{$declare}(fn (RestRequest $request, Resource $resource): bool => $resource instanceof CategoryResource && $request->boolean('strict'));

        $this->assertTrue($relation->{$evaluate}(new RestRequest(['strict' => '1'])));
        $this->assertFalse($relation->{$evaluate}(new RestRequest(['strict' => '0'])));
    }

    #[Test]
    #[DataProvider('constraints')]
    public function it_returns_the_relation_type_from_a_constraint_setter(string $declare, string $evaluate): void
    {
        $relation = HasMany::make('products', ProductResource::class);

        $this->assertSame($relation, $relation->{$declare}());
        $this->assertTrue($relation->{$evaluate}(new RestRequest));
        $this->assertSame('static', (string) (new ReflectionMethod(HasMany::class, $declare))->getReturnType());
    }
}
