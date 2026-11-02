<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Validation\Cursor;
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
        $related = $relations === null ? null : $relations[array_key_last($relations)]->resource();
        $body = $name === null || $related === null ? null : $this->body($entry, $name, $related, $context);

        $rules->add($relation->path, ['required', new KnownRelation($context->resource)]);
        $rules->add($entry->at('text')->path, ['prohibited']);
        $rules->add($entry->at('trashed')->path, ['prohibited']);
        $rules->addWhenPresent($alias, [
            'string',
            'max:255',
            'regex:'.RowKeys::PATTERN,
            new IncludeAlias($keys, $name ?? ''),
        ]);

        $search = $body?->node(null, null);

        if ($name === null || $relations === null || $search === null || ($alias->value !== null && ! is_string($alias->value))) {
            $rules->partial();

            return null;
        }

        return new IncludeNode($name, $relations, $related, is_string($alias->value) ? $alias->value : null, $search);
    }

    private function body(Cursor $entry, string $relation, Resource $related, ValidationContext $context): ?SearchBodyParser
    {
        $levels = count(explode('.', $relation));
        $limit = $context->limits->depths->includes;

        if ($context->includeDepth + $levels > $limit) {
            $context->rules->reject(Violation::tooDeep($entry->at('relation')->path, $limit));

            return null;
        }

        return SearchBodyParser::keys($entry, $context->related($related)->included($levels), false);
    }
}
