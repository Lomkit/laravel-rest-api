<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Closure;
use LogicException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Search\FieldSort;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\RelevanceSort;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Text\Engine\EngineCriteria;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;
use Lomkit\Rest\Text\Engine\EngineSort;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EngineCriteriaTest extends PackageTestCase
{
    /** @param Closure(): EngineCriteria $criteria */
    #[Test]
    #[DataProvider('foreignNodes')]
    public function it_refuses_a_node_of_another_grammar(Closure $criteria, string $node): void
    {
        $this->expectExceptionObject(new LogicException($node.' does not apply to a search engine query.'));

        $criteria();
    }

    /** @return iterable<string, array{Closure(): EngineCriteria, class-string}> */
    public static function foreignNodes(): iterable
    {
        yield 'a database filter group' => [
            static fn (): EngineCriteria => EngineCriteria::of(new FilterGroup([new FilterGroup]), []),
            FilterGroup::class,
        ];
        yield 'a field sort' => [
            static fn (): EngineCriteria => EngineCriteria::of(new FilterGroup, [new FieldSort(Integer::make('price'), null)]),
            FieldSort::class,
        ];
    }

    #[Test]
    public function it_takes_the_nodes_of_the_engine_grammar(): void
    {
        $criteria = EngineCriteria::of(
            new FilterGroup([new EngineFilterCondition('price', new IndexAttribute(Integer::make('price'), [FilterOperator::Equal]), FilterOperator::Equal, 1)]),
            [new RelevanceSort(null), new EngineSort('price', SortDirection::Desc)],
        );

        $this->assertInstanceOf(EngineCriteria::class, $criteria);
    }
}
