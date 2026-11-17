<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use LogicException;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\FilterNode;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;

/** @internal */
final readonly class FilterTree
{
    /** @param non-empty-list<FilterTree|FilterLeaf> $members */
    public function __construct(
        public bool $any,
        public array $members,
    ) {}

    public static function needed(FilterGroup $group): bool
    {
        foreach ($group->filters as $index => $node) {
            if ($node instanceof FilterGroup || ($index > 0 && $node->boolean() === 'or')) {
                return true;
            }
        }

        return false;
    }

    public static function of(FilterGroup $group): ?self
    {
        $runs = [];
        $run = [];

        foreach ($group->filters as $node) {
            $member = self::member($node);

            if ($member === null) {
                continue;
            }

            if ($run !== [] && $node->boolean() === 'or') {
                $runs[] = $run;
                $run = [];
            }

            $run[] = $member;
        }

        if ($run === []) {
            return null;
        }

        $runs[] = $run;

        return count($runs) === 1 ? new self(false, $run) : new self(true, array_map(fn (array $members): self|FilterLeaf => count($members) === 1 ? $members[0] : new self(false, $members), $runs));
    }

    private static function member(FilterNode $node): self|FilterLeaf|null
    {
        return match (true) {
            $node instanceof FilterGroup => self::of($node),
            $node instanceof EngineFilterCondition => FilterLeaf::of($node),
            default => throw new LogicException($node::class.' does not apply to a search engine query.'),
        };
    }
}
