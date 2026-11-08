<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Contracts\OwnerRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\MorphIncludeNode;
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
     * @return list<IncludeNode|MorphIncludeNode>
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

    private function include(Cursor $entry, RowKeys $keys, ValidationContext $context): IncludeNode|MorphIncludeNode|null
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $rules = $context->rules;
        $relation = $entry->at('relation');
        $alias = $entry->at('alias');
        $name = is_string($relation->value) ? $relation->value : null;
        $relations = $name === null ? null : $context->reachRelation($name, $entry->path);
        $through = $relations === null ? null : $relations[array_key_last($relations)];

        $context->reader->known($entry, $through instanceof PolymorphicRelation ? [...self::KEYS, 'types' => true] : self::KEYS);

        $levels = $name === null || $relations === null ? null : $this->levels($entry, $name, $relations, $context);
        $body = $levels === null || $through === null || $through instanceof PolymorphicRelation ? null : self::body($entry, $through, $levels);

        $rules->add($relation->path, ['required', new KnownRelation($context->resource)]);
        $rules->add($entry->at('text')->path, ['prohibited']);
        $rules->add($entry->at('trashed')->path, ['prohibited']);

        if ($levels !== null && $through instanceof OwnerRelation) {
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

        $aliased = is_string($alias->value) ? $alias->value : null;
        $search = $body?->node(null, null);
        $node = match (true) {
            $name === null || $relations === null || $levels === null => null,
            $through instanceof PolymorphicRelation => (new MorphIncludeParser)->parse($entry, $name, $relations, $through, $aliased, $levels),
            $search === null => null,
            default => new IncludeNode($name, $relations, $relations[array_key_last($relations)]->resource(), $aliased, $search),
        };

        if ($node === null || ($alias->value !== null && $aliased === null)) {
            $rules->partial();

            return null;
        }

        return $node;
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
    private function levels(Cursor $entry, string $relation, array $relations, ValidationContext $context): ?ValidationContext
    {
        $levels = count(explode('.', $relation));
        $limit = $context->limits->depths->includes;

        if ($context->includeDepth + $levels > $limit) {
            $context->rules->reject(Violation::tooDeep($entry->at('relation')->path, $limit));

            return null;
        }

        array_pop($relations);

        foreach ($relations as $middle) {
            if (! $middle instanceof OwnerRelation) {
                $resource = $middle->resource();

                $resource->definition()->limits();
            }
        }

        return $context->included($levels);
    }

    private static function body(Cursor $entry, Relation $through, ValidationContext $context): SearchBodyParser
    {
        $context = $context
            ->related($through->resource())
            ->reserving($through->reservedKeys());

        return SearchBodyParser::keys($entry, $context, $through instanceof OwnerRelation ? LevelBounds::Owner : LevelBounds::Limited);
    }
}
