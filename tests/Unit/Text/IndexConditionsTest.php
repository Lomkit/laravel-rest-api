<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\IndexConditions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('scout')]
class IndexConditionsTest extends TestCase
{
    #[Test]
    public function it_matches_nothing_once_asked(): void
    {
        $conditions = new IndexConditions;
        $conditions->include('number', [1, 2]);

        $this->assertFalse($conditions->matchesNothing());

        $conditions->nothing();
        $conditions->include('number', [1]);

        $this->assertTrue($conditions->matchesNothing());
        $this->assertSame(['number' => [1]], $this->applied($conditions)->whereIns);
    }

    #[Test]
    public function it_still_intersects_the_lists_of_a_single_valued_attribute(): void
    {
        $conditions = new IndexConditions;
        $conditions->include('number', [1, 2, 3]);
        $conditions->include('number', [3, 2, 4]);
        $conditions->exclude('team', ['blue']);
        $conditions->exclude('team', ['green', 'blue']);
        $conditions->compare('price', FilterOperator::LessThan, 20);

        $builder = $this->applied($conditions);

        $this->assertSame(['number' => [2, 3]], $builder->whereIns);
        $this->assertSame(['team' => ['blue', 'green']], $builder->whereNotIns);
        $this->assertSame([['field' => 'price', 'operator' => '<', 'value' => 20]], $builder->wheres);
        $this->assertFalse($conditions->matchesNothing());
    }

    #[Test]
    public function it_keeps_the_narrower_list_of_a_list_attribute_in_any_order(): void
    {
        $narrowed = new IndexConditions;
        $narrowed->include('readers', ['ann', 'bob'], true);
        $narrowed->include('readers', ['ann'], true);
        $widened = new IndexConditions;
        $widened->include('readers', ['ann'], true);
        $widened->include('readers', ['bob', 'ann'], true);

        $this->assertSame(['readers' => ['ann']], $this->applied($narrowed)->whereIns);
        $this->assertSame(['readers' => ['ann']], $this->applied($widened)->whereIns);
    }

    /** @param list<list<string>> $lists */
    #[Test]
    #[DataProvider('overlappingLists')]
    public function it_refuses_two_lists_of_a_list_attribute_that_neither_holds(array $lists): void
    {
        $conditions = new IndexConditions;
        $conditions->include('readers', $lists[0], true);

        $this->expectExceptionObject(InvalidEngineQueryException::conflictingListMemberships('readers'));

        $conditions->include('readers', $lists[1], true);
    }

    /** @return array<string, array{list<list<string>>}> */
    public static function overlappingLists(): array
    {
        return [
            'one order' => [[['ann', 'bob'], ['ann', 'cid']]],
            'the other order' => [[['ann', 'cid'], ['ann', 'bob']]],
        ];
    }

    #[Test]
    public function it_keeps_an_empty_list_attribute_matching_nothing(): void
    {
        $conditions = new IndexConditions;
        $conditions->include('readers', ['ann'], true);
        $conditions->include('readers', [], true);

        $this->assertTrue($conditions->matchesNothing());
    }

    #[Test]
    public function it_adds_up_the_excluded_values_of_a_list_attribute(): void
    {
        $conditions = new IndexConditions;
        $conditions->exclude('readers', ['ann']);
        $conditions->exclude('readers', ['bob', 'ann']);

        $this->assertSame(['readers' => ['ann', 'bob']], $this->applied($conditions)->whereNotIns);
    }

    /** @return Builder<Model> */
    private function applied(IndexConditions $conditions): Builder
    {
        $builder = new Builder(new class extends Model {}, '');
        $conditions->applyTo($builder);

        return $builder;
    }
}
