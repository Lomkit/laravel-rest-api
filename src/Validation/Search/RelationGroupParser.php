<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\RelationFilterGroup;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class RelationGroupParser
{
    private const array KEYS = ['relation' => true, 'exists' => true, 'type' => true, 'nested' => true];

    public function __construct(
        private FilterParser $filters,
    ) {}

    public function parse(Cursor $entry, ValidationContext $context): ?RelationFilterGroup
    {
        $context->reader->known($entry, self::KEYS);

        $relation = $this->relation($entry, $context);
        $exists = $entry->at('exists');
        $type = $entry->at('type');

        $context->rules->addWhenPresent($exists, ['boolean:strict']);
        $context->rules->addWhenPresent($type, [Rule::in(['or', 'and'])]);
        $boolean = $this->filters->boolean($type, $context);

        if ($relation === null) {
            return $this->partial($context);
        }

        $entries = $this->nested($entry, $context);
        $filters = $entries === null ? null : $this->filters->group($entries, $context->for($relation->resource())->deeper());

        if ($filters === null || ($exists->present && ! is_bool($exists->value))) {
            return $this->partial($context);
        }

        return new RelationFilterGroup($relation, $exists->value !== false, $filters, $boolean);
    }

    private function relation(Cursor $entry, ValidationContext $context): ?Relation
    {
        $resource = $context->resource;
        $name = $entry->at('relation');
        $names = $context->vocabulary->of($resource, $context->grammar, 'relations', fn (): array => array_values(array_map(
            fn (Relation $relation): string => $relation->relation,
            $resource->getRelations(),
        )));

        $context->rules->add($name->path, ['required', 'string', $names]);

        if (! is_string($name->value) || ! $names->accepts($name->value)) {
            return null;
        }

        $relation = $resource->relation($name->value);

        if ($relation !== null) {
            $context->rules->reach(Check::onClass($relation->resource(), 'viewAny', $entry->path));
        }

        return $relation;
    }

    /** @return list<Cursor>|null */
    private function nested(Cursor $entry, ValidationContext $context): ?array
    {
        $nested = $entry->at('nested');

        if ($context->filterDepth >= $context->limits->depths->filters) {
            $context->rules->add($nested->path, ['prohibited']);

            return $context->reader->filled($nested) ? null : [];
        }

        if (! $nested->present) {
            return [];
        }

        $context->rules->add($nested->path, ['array']);

        return $context->reader->entries($nested, $context->limits->sizes->filters, true);
    }

    private function partial(ValidationContext $context): null
    {
        $context->rules->partial();

        return null;
    }
}
