<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

/** @internal */
final readonly class EloquentFilter
{
    /** @param Builder<Model> $query */
    public static function apply(Builder $query, FilterTree $tree, string $boolean = 'and'): void
    {
        $query->where(function (Builder $group) use ($tree): void {
            foreach ($tree->members as $member) {
                $joint = $tree->any ? 'or' : 'and';

                if ($member instanceof FilterTree) {
                    self::apply($group, $member, $joint);

                    continue;
                }

                match ($member->operator) {
                    FilterOperator::In => $group->whereIn($member->attribute, $member->members(), $joint),
                    FilterOperator::NotIn => $group->whereNotIn($member->attribute, $member->members(), $joint),
                    default => $group->where($member->attribute, $member->operator->value, $member->scalar(), $joint),
                };
            }
        }, null, null, $boolean);
    }
}
