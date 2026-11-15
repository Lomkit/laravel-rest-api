<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\IndexConditions;
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

    /** @return Builder<Model> */
    private function applied(IndexConditions $conditions): Builder
    {
        $builder = new Builder(new class extends Model {}, '');
        $conditions->applyTo($builder);

        return $builder;
    }
}
