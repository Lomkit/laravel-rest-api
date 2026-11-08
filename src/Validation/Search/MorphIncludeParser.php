<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\MorphIncludeNode;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class MorphIncludeParser
{
    public const array SEARCH_KEYS = ['selects', 'filters', 'scopes', 'includes', 'aggregates', 'instructions', 'gates'];

    /** @param non-empty-list<Relation> $relations */
    public function parse(Cursor $entry, string $name, array $relations, Relation&PolymorphicRelation $relation, ?string $alias, ValidationContext $context): ?MorphIncludeNode
    {
        $blocks = $this->blocks($entry, $relation, $context);

        if ($blocks === null) {
            $context->rules->partial();

            return null;
        }

        $branches = [];

        foreach ($blocks as $type => $block) {
            $target = $relation->targets()->forType($type);

            if ($target === null) {
                continue;
            }

            $resource = $relation->resourceOf($target);
            $context->rules->reach(Check::onClass($resource, 'viewAny', $entry->path));
            $search = $block === null ? SearchNode::empty() : $this->search($block, $block->path !== $entry->path, $context->related($resource)->reserving($relation->reservedKeys()));
            $branches[$target->type] = new IncludeNode($name, [$relation], $resource, $alias, $search);
        }

        if ($branches === []) {
            $context->rules->partial();

            return null;
        }

        return new MorphIncludeNode($name, $relations, $relation, $alias, $branches);
    }

    /** @return array<string, Cursor|null>|null */
    private function blocks(Cursor $entry, PolymorphicRelation $relation, ValidationContext $context): ?array
    {
        $targets = $relation->targets();
        $types = $entry->at('types');
        $flat = array_values(array_filter(self::SEARCH_KEYS, static fn (string $key): bool => $entry->at($key)->present));

        if ($targets->isPolymorphic()) {
            foreach ($flat as $key) {
                $context->rules->reject(Violation::severalTypes($entry->at($key)->path));
            }
        }

        if (! $types->present) {
            return $targets->isPolymorphic() ? array_fill_keys($targets->types(), null) : [$targets->types()[0] => $entry];
        }

        $context->rules->add($types->path, ['array', 'min:1', ...array_map(static fn (string $key): string => 'prohibits:'.$entry->at($key)->path, $targets->isPolymorphic() ? [] : $flat)]);

        if (! $context->reader->object($types)) {
            return null;
        }

        $blocks = [];

        foreach ($context->reader->among($types, $targets->types()) as $type => $block) {
            if ($context->reader->object($block)) {
                $blocks[$type] = $block;
            }
        }

        return $blocks;
    }

    private function search(Cursor $block, bool $typed, ValidationContext $context): SearchNode
    {
        if (! $typed) {
            return SearchBodyParser::keys($block, $context, LevelBounds::Owner)->node(null, null);
        }

        $context->reader->refuse($block, ['relation', 'alias', 'types', 'page', 'text', 'trashed']);

        foreach (['limit', 'sorts'] as $key) {
            if ($block->at($key)->present) {
                $context->rules->reject(Violation::ownerInclude($block->at($key)->path));
            }
        }

        return SearchBodyParser::keys($block, $context, LevelBounds::Owner)->node(null, null);
    }
}
