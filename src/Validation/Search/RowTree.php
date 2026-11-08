<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\MorphIncludeNode;
use Lomkit\Rest\Validation\Cursor;

final readonly class RowTree
{
    /**
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @return list<RowNode>
     */
    public static function of(Cursor $level, array $includes): array
    {
        return self::level(RowStep::of($level, $includes));
    }

    /**
     * @param  list<RowStep>  $steps
     * @return list<RowNode>
     */
    private static function level(array $steps): array
    {
        $entries = [];
        $groups = [];

        foreach ($steps as $step) {
            if ($step->include->alias !== null) {
                $entries[] = self::node($step, []);

                continue;
            }

            $segment = $step->segment();

            if (! array_key_exists($segment, $groups)) {
                $entries[] = $segment;
                $groups[$segment] = ['first' => $step, 'level' => null, 'children' => []];
            }

            if ($step->isLast()) {
                $groups[$segment]['level'] = $step;

                continue;
            }

            $groups[$segment]['children'][] = $step->next();
        }

        return array_map(
            static fn (RowNode|string $entry): RowNode => is_string($entry) ? self::group($groups[$entry]['first'], $groups[$entry]['level'], $groups[$entry]['children']) : $entry,
            $entries,
        );
    }

    /** @param list<RowStep> $children */
    private static function group(RowStep $first, ?RowStep $level, array $children): RowNode
    {
        if ($level !== null) {
            return self::node($level, $children);
        }

        $relation = $first->relation();

        return new RowNode($first->entry->at('relation')->path, self::perParent($relation, null), [self::level($children)]);
    }

    /** @param list<RowStep> $children */
    private static function node(RowStep $step, array $children): RowNode
    {
        $include = $step->include;
        $entry = $step->entry;

        if ($include instanceof MorphIncludeNode) {
            return new RowNode($entry->at('relation')->path, 1, self::branches($include, $entry, $children));
        }

        $relation = $step->relation();
        $limit = $include->search->limit;
        $key = $relation->hasMultipleEntries() && $limit !== null ? $entry->at('limit') : $entry->at('relation');

        return new RowNode($key->path, self::perParent($relation, $limit, $include), [self::level([...$children, ...RowStep::of($entry, $include->search->includes)])]);
    }

    /**
     * @param  list<RowStep>  $children
     * @return list<list<RowNode>>
     */
    private static function branches(MorphIncludeNode $include, Cursor $entry, array $children): array
    {
        $types = $entry->at('types');
        $branches = [];

        foreach ($include->branches as $type => $branch) {
            $block = $types->present ? $types->at($type) : $entry;
            $branches[] = self::level([...$children, ...RowStep::of($block, $branch->search->includes)]);
        }

        return $branches;
    }

    private static function perParent(Relation $relation, ?int $limit, ?IncludeNode $include = null): int
    {
        if (! $relation->hasMultipleEntries()) {
            return 1;
        }

        $resource = $include === null ? $relation->resource() : $include->resource;

        return $limit ?? $resource->definition()
            ->limits()
            ->includeLimit;
    }
}
