<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Closure;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\IndexLeaves;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\ScoutModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineConditionGroupTest extends EngineTestCase
{
    /** @param Closure(EngineConditionGroup): mixed $conditions */
    #[Test]
    #[DataProvider('foldedGroups')]
    public function it_folds_the_constant_conditions_of_a_group(bool $any, Closure $conditions, FilterTree|FilterLeaf|bool $expected): void
    {
        $this->assertEquals($expected, EngineConditionGroup::compile($this->leaves(), $any, $conditions));
    }

    /** @return array<string, array{bool, Closure(EngineConditionGroup): mixed, FilterTree|FilterLeaf|bool}> */
    public static function foldedGroups(): array
    {
        $five = new FilterLeaf('number', FilterOperator::Equal, 5);
        $four = new FilterLeaf('number', FilterOperator::Equal, 4);
        $active = new FilterLeaf('active', FilterOperator::Equal, true);

        return [
            'an empty or group matches nothing' => [true, fn (EngineConditionGroup $group): null => null, false],
            'an empty and group matches nothing' => [false, fn (EngineConditionGroup $group): null => null, false],
            'a true condition opens an or group' => [true, fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 5)->whereNotIn('number', []), true],
            'a false condition closes an and group' => [false, fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 5)->whereIn('number', []), false],
            'an or group of false conditions matches nothing' => [true, fn (EngineConditionGroup $group): EngineConditionGroup => $group->whereIn('number', [])->whereIn('price', []), false],
            'an and group of true conditions matches everything' => [false, fn (EngineConditionGroup $group): EngineConditionGroup => $group->whereNotIn('number', []), true],
            'an empty sub-group is left out of an or group' => [true, fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 5)->whereAll(fn (EngineConditionGroup $all): null => null), $five],
            'an or group inside an or group' => [true, fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 5)->whereAny(fn (EngineConditionGroup $any): EngineConditionGroup => $any->where('number', '=', 4)->where('active', '=', true)), new FilterTree(true, [$five, new FilterTree(true, [$four, $active])])],
            'an and group inside an and group' => [false, fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 5)->whereAll(fn (EngineConditionGroup $all): EngineConditionGroup => $all->where('number', '=', 4)->where('active', '=', true)), new FilterTree(false, [$five, new FilterTree(false, [$four, $active])])],
        ];
    }

    #[Test]
    public function it_refuses_a_condition_added_to_a_group_after_its_closure(): void
    {
        $kept = null;
        EngineConditionGroup::compile($this->leaves(), true, function (EngineConditionGroup $group) use (&$kept): void {
            $group->where('number', '=', 5)->whereAll(function (EngineConditionGroup $all) use (&$kept): void {
                $kept = $all->where('active', '=', true);
            });
        });

        $this->expectExceptionObject(InvalidEngineQueryException::closedGroup());

        $kept?->where('number', '=', 4);
    }

    private function leaves(): IndexLeaves
    {
        $target = $this->target(VoucherResource::class);
        $profile = EngineProfiles::forTarget($target);
        $model = ScoutModel::tryFrom(new Voucher) ?? $this->fail('The voucher model is not searchable.');

        return new IndexLeaves(new EngineIndex($model, $profile, IndexAttributes::of($target, $profile, ['number', 'price', 'active'], [], []), VoucherResource::class));
    }
}
