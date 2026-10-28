<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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
}
