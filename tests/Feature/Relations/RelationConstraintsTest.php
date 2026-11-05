<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\StrictCategoryConstraint;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class RelationConstraintsTest extends PackageTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function constraints(): iterable
    {
        yield 'required on creation' => ['requiredOnCreation', 'isRequiredOnCreation'];
        yield 'prohibited on creation' => ['prohibitedOnCreation', 'isProhibitedOnCreation'];
        yield 'required on update' => ['requiredOnUpdate', 'isRequiredOnUpdate'];
        yield 'prohibited on update' => ['prohibitedOnUpdate', 'isProhibitedOnUpdate'];
    }

    /** @return iterable<string, array{string, string, callable(RestRequest, Resource): bool}> */
    public static function constraintsWithCallables(): iterable
    {
        $callables = [
            'a closure' => fn (RestRequest $request, Resource $resource): bool => $resource instanceof CategoryResource && $request->boolean('strict'),
            'an invokable object' => new StrictCategoryConstraint,
            'a Class::method string' => StrictCategoryConstraint::class.'::check',
            'an [object, method] array' => [new StrictCategoryConstraint, 'evaluate'],
        ];

        foreach (self::constraints() as $constraint => [$declare, $evaluate]) {
            foreach ($callables as $kind => $callable) {
                yield "{$constraint} with {$kind}" => [$declare, $evaluate, $callable];
            }
        }
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function constraintsWithBools(): iterable
    {
        foreach (self::constraints() as $constraint => [$declare, $evaluate]) {
            yield "{$constraint} set to true" => [$declare, $evaluate, true];
            yield "{$constraint} set to false" => [$declare, $evaluate, false];
        }
    }

    /** @param callable(RestRequest, Resource): bool $callback */
    #[Test]
    #[DataProvider('constraintsWithCallables')]
    public function it_passes_the_request_and_the_related_resource_to_a_constraint_callable(string $declare, string $evaluate, callable $callback): void
    {
        $strict = $this->categoryRelation(['strict' => '1'])->{$declare}($callback);
        $lenient = $this->categoryRelation(['strict' => '0'])->{$declare}($callback);

        $this->assertTrue($strict->{$evaluate}());
        $this->assertFalse($lenient->{$evaluate}());
    }

    #[Test]
    #[DataProvider('constraintsWithBools')]
    public function it_returns_a_constraint_set_to_a_bool_as_is(string $declare, string $evaluate, bool $value): void
    {
        $relation = $this->categoryRelation()->{$declare}(fn (): bool => ! $value)->{$declare}($value);

        $this->assertSame($value, $relation->{$evaluate}());
    }

    #[Test]
    #[DataProvider('constraints')]
    public function it_casts_the_result_of_a_constraint_callback_to_a_bool(string $declare, string $evaluate): void
    {
        $callback = fn (RestRequest $request): ?string => $request->input('strict');

        $answered = $this->categoryRelation(['strict' => 'yes'])->{$declare}($callback);
        $unanswered = $this->categoryRelation()->{$declare}($callback);

        $this->assertTrue($answered->{$evaluate}());
        $this->assertFalse($unanswered->{$evaluate}());
    }

    #[Test]
    #[DataProvider('constraints')]
    public function it_leaves_the_other_constraints_unset(string $declare, string $evaluate): void
    {
        $relation = $this->categoryRelation()->{$declare}();

        foreach (self::constraints() as [, $reader]) {
            $this->assertSame($reader === $evaluate, $relation->{$reader}());
        }
    }

    #[Test]
    #[DataProvider('constraints')]
    public function it_returns_the_relation_type_from_a_constraint_setter(string $declare, string $evaluate): void
    {
        $relation = HasMany::make('products', ProductResource::class);

        $this->assertSame($relation, $relation->{$declare}());
        $this->assertTrue($relation->{$evaluate}());
        $this->assertSame('static', (string) (new ReflectionMethod(HasMany::class, $declare))->getReturnType());
    }

    /** @param array<string, string> $input */
    private function categoryRelation(array $input = []): BelongsTo
    {
        $context = new RestContext(new RestRequest($input), $this->app);

        return BelongsTo::make('category', CategoryResource::class)->fromResource($context->resource(ProductResource::class));
    }
}
