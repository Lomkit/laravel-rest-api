<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Exceptions\ConflictingListsException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\IndexConditions;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\NullProfile;
use Lomkit\Rest\Text\Engine\Profiles\TurbopufferProfile;
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
        $conditions = new IndexConditions(ReaderVoucherResource::class);
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
        $conditions = new IndexConditions(ReaderVoucherResource::class);
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
        $narrowed = new IndexConditions(ReaderVoucherResource::class);
        $narrowed->include('readers', ['ann', 'bob'], true);
        $narrowed->include('readers', ['ann'], true);
        $widened = new IndexConditions(ReaderVoucherResource::class);
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
        $conditions = new IndexConditions(ReaderVoucherResource::class);
        $conditions->include('readers', $lists[0], true);

        $this->expectExceptionObject(ConflictingListsException::memberships(ReaderVoucherResource::class, 'readers', null, null));

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
        $conditions = new IndexConditions(ReaderVoucherResource::class);
        $conditions->include('readers', ['ann'], true);
        $conditions->include('readers', [], true);

        $this->assertTrue($conditions->matchesNothing());
    }

    #[Test]
    public function it_adds_up_the_excluded_values_of_a_list_attribute(): void
    {
        $conditions = new IndexConditions(ReaderVoucherResource::class);
        $conditions->exclude('readers', ['ann']);
        $conditions->exclude('readers', ['bob', 'ann']);

        $this->assertSame(['readers' => ['ann', 'bob']], $this->applied($conditions)->whereNotIns);
    }

    #[Test]
    public function it_hands_the_list_conditions_to_a_profile_that_filters_lists_natively(): void
    {
        $conditions = new IndexConditions(ReaderVoucherResource::class);
        $conditions->include('number', [1, 2]);
        $conditions->include('readers', ['ann'], true);
        $conditions->exclude('team', ['blue']);
        $conditions->exclude('labels', ['x', 'y'], true);

        $builder = $this->applied($conditions, new TurbopufferProfile(new FakeTurbopufferEngine));

        $this->assertSame(['number' => [1, 2]], $builder->whereIns);
        $this->assertSame(['team' => ['blue']], $builder->whereNotIns);
        $this->assertSame(['filters' => ['And', [['readers', 'ContainsAny', ['ann']], ['labels', 'NotContainsAny', ['x', 'y']]]]], $builder->options);
    }

    #[Test]
    public function it_sends_a_single_list_condition_as_the_only_native_filter_beside_the_options(): void
    {
        $conditions = new IndexConditions(ReaderVoucherResource::class);
        $conditions->exclude('readers', ['ann'], true);
        $builder = new Builder(new class extends Model {}, '');
        $builder->options(['consistency' => ['level' => 'eventual']]);

        $conditions->applyTo($builder, new TurbopufferProfile(new FakeTurbopufferEngine));

        $this->assertSame([[], []], [$builder->whereIns, $builder->whereNotIns]);
        $this->assertSame(['consistency' => ['level' => 'eventual'], 'filters' => ['readers', 'NotContainsAny', ['ann']]], $builder->options);
    }

    #[Test]
    public function it_sets_no_native_filter_without_a_list_condition(): void
    {
        $conditions = new IndexConditions(ReaderVoucherResource::class);
        $conditions->include('number', [1]);

        $builder = $this->applied($conditions, new TurbopufferProfile(new FakeTurbopufferEngine));

        $this->assertSame([['number' => [1]], []], [$builder->whereIns, $builder->options]);
    }

    /** @return Builder<Model> */
    private function applied(IndexConditions $conditions, ?EngineProfile $profile = null): Builder
    {
        $builder = new Builder(new class extends Model {}, '');
        $conditions->applyTo($builder, $profile ?? new NullProfile(new NullEngine));

        return $builder;
    }
}
