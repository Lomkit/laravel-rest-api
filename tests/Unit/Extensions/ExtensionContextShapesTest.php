<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Lomkit\Rest\Extensions\Contexts\EngineConditionGroup;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Contexts\EngineRestriction;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\GatesContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class ExtensionContextShapesTest extends TestCase
{
    /**
     * @param  class-string  $context
     * @param  list<string>  $properties
     */
    #[Test]
    #[DataProvider('contexts')]
    public function it_hands_each_extension_only_the_properties_of_its_context(string $context, array $properties): void
    {
        $this->assertSame($properties, array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($context))->getProperties(ReflectionProperty::IS_PUBLIC),
        ));
    }

    /** @return array<string, array{class-string, list<string>}> */
    public static function contexts(): array
    {
        return [
            'a gate declaration' => [GatesContext::class, ['resource']],
            'an index attribute declaration' => [IndexAttributesContext::class, ['resource', 'matchesStringLists']],
            'an engine profile provider' => [EngineProfileContext::class, ['resource', 'engine']],
            'an engine restriction' => [EngineRestrictionContext::class, ['query', 'resource', 'user', 'matchesStringLists', 'rowsRestrictedInSql', 'comparesColumns', 'engine', 'filtersWithGroups']],
        ];
    }

    #[Test]
    public function it_offers_only_where_clauses_groups_and_match_nothing_to_an_engine_restriction(): void
    {
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(EngineRestriction::class))->getMethods(ReflectionMethod::IS_PUBLIC));

        $this->assertSame(['where', 'whereIn', 'whereNotIn', 'whereAny', 'matchNothing'], array_values(array_diff($methods, ['__construct'])));
    }

    #[Test]
    public function it_offers_only_conditions_and_sub_groups_to_an_engine_condition_group(): void
    {
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(EngineConditionGroup::class))->getMethods(ReflectionMethod::IS_PUBLIC));

        $this->assertSame(['where', 'whereIn', 'whereNotIn', 'whereAll', 'whereAny'], array_values(array_diff($methods, ['compile'])));
    }
}
