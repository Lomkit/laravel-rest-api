<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Contracts\OwnerRelation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class IncludeParser
{
    private const array KEYS = [
        'relation' => true,
        'alias' => true,
        'limit' => true,
        'filters' => true,
        'gates' => true,
        'scopes' => true,
        'sorts' => true,
        'selects' => true,
        'aggregates' => true,
        'includes' => true,
        'instructions' => true,
        'text' => true,
        'trashed' => true,
    ];

    /**
     * @param  list<Cursor>  $entries
     * @return list<IncludeNode>
     */
    public function parse(array $entries, ValidationContext $context, RowKeys $keys): array
    {
        $includes = [];

        foreach ($entries as $entry) {
            $include = $this->include($entry, $keys, $context);

            if ($include !== null) {
                $includes[] = $include;
            }
        }

        return $includes;
    }

    private function include(Cursor $entry, RowKeys $keys, ValidationContext $context): ?IncludeNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, self::KEYS);

        $rules = $context->rules;
        $relation = $entry->at('relation');
        $alias = $entry->at('alias');
        $name = is_string($relation->value) ? $relation->value : null;
        $relations = $name === null ? null : $context->reachRelation($name, $entry->path);
        $through = $relations === null ? null : $relations[array_key_last($relations)];
        $related = $through?->resource();
        $body = $name === null || $relations === null ? null : $this->body($entry, $name, $relations, $context);

        $rules->add($relation->path, ['required', new KnownRelation($context->resource)]);
        $rules->add($entry->at('text')->path, ['prohibited']);
        $rules->add($entry->at('trashed')->path, ['prohibited']);

        if ($body !== null && $through instanceof OwnerRelation) {
            self::refuseBounds($entry, $rules);
        }

        $rules->addWhenPresent($alias, [
            'string',
            'max:255',
            'regex:'.RowKeys::PATTERN,
            new IncludeAlias($keys, $name ?? ''),
        ]);

        if (! $alias->present && $name !== null && $keys->isTaken(RowKeys::includeKey($name))) {
            $rules->reject(Violation::takenKey($alias->path, RowKeys::includeKey($name)));
        }

        $search = $body?->node(null, null);

        if ($name === null || $relations === null || $related === null || $search === null || ($alias->value !== null && ! is_string($alias->value))) {
            $rules->partial();

            return null;
        }

        return new IncludeNode($name, $relations, $related, is_string($alias->value) ? $alias->value : null, $search);
    }

    private static function refuseBounds(Cursor $entry, ParsedRules $rules): void
    {
        foreach (['limit', 'sorts'] as $key) {
            if ($entry->at($key)->present) {
                $rules->reject(Violation::ownerInclude($entry->at($key)->path));
            }
        }
    }

    /** @param non-empty-list<Relation> $relations */
    private function body(Cursor $entry, string $relation, array $relations, ValidationContext $context): ?SearchBodyParser
    {
        $levels = count(explode('.', $relation));
        $limit = $context->limits->depths->includes;

        if ($context->includeDepth + $levels > $limit) {
            $context->rules->reject(Violation::tooDeep($entry->at('relation')->path, $limit));

            return null;
        }

        $through = array_pop($relations);

        foreach ($relations as $middle) {
            if (! $middle instanceof OwnerRelation) {
                $resource = $middle->resource();

                $resource->context()
                    ->definition($resource)
                    ->limits();
            }
        }

        $context = $context
            ->related($through->resource())
            ->included($levels)
            ->reserving($through->reservedKeys());

        return SearchBodyParser::keys($entry, $context, $through instanceof OwnerRelation ? LevelBounds::Owner : LevelBounds::Limited);
    }
}
