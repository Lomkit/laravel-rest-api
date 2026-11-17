<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Closure;
use LogicException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Groups\MeilisearchFilter;
use Lomkit\Rest\Text\Engine\Groups\TurbopufferFilter;
use Lomkit\Rest\Text\Engine\Groups\TypesenseFilter;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterTreeTest extends TestCase
{
    #[Test]
    public function it_binds_and_tighter_than_or_and_skips_an_empty_group(): void
    {
        $tree = FilterTree::of(new FilterGroup([
            self::number(1, 'or'),
            self::number(2, 'or'),
            self::number(3),
            new FilterGroup([], 'and'),
            new FilterGroup([self::number(4), self::number(5, 'or')], 'or'),
        ]));

        $this->assertSame('(number:=1 || (number:=2 && number:=3) || (number:=4 || number:=5))', $tree === null ? null : TypesenseFilter::of($tree));
        $this->assertNull(FilterTree::of(new FilterGroup([new FilterGroup])));
        $this->assertFalse(FilterTree::needed(new FilterGroup([self::number(1, 'or'), self::number(2)])));
        $this->assertTrue(FilterTree::needed(new FilterGroup([self::number(1), self::number(2, 'or')])));
    }

    /** @param Closure(FilterLeaf): mixed $write */
    #[Test]
    #[DataProvider('writers')]
    public function it_refuses_an_operator_that_a_writer_does_not_write(Closure $write, string $engine): void
    {
        $this->expectExceptionObject(new LogicException("The [like] operator does not apply to a {$engine} filter."));

        $write(new FilterLeaf('code', FilterOperator::Like, 'V-%'));
    }

    /** @return array<string, array{Closure(FilterLeaf): mixed, string}> */
    public static function writers(): array
    {
        return [
            'typesense' => [static fn (FilterLeaf $leaf): string => TypesenseFilter::of($leaf), 'Typesense'],
            'meilisearch' => [static fn (FilterLeaf $leaf): string => MeilisearchFilter::of($leaf), 'Meilisearch'],
            'turbopuffer' => [static fn (FilterLeaf $leaf): array => TurbopufferFilter::of($leaf, 'id'), 'Turbopuffer'],
        ];
    }

    private static function number(int $value, string $boolean = 'and'): EngineFilterCondition
    {
        return new EngineFilterCondition('number', new IndexAttribute(Integer::make('number'), [FilterOperator::Equal]), FilterOperator::Equal, $value, $boolean);
    }
}
