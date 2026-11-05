<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\MorphFilterGroup;
use Lomkit\Rest\Search\RelationFilterGroup;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class RelationGroupParser
{
    private const array KEYS = ['relation' => true, 'exists' => true, 'type' => true, 'nested' => true];

    public function __construct(
        private FilterParser $filters,
    ) {}

    public function parse(Cursor $entry, ValidationContext $context): RelationFilterGroup|MorphFilterGroup|null
    {
        $name = $entry->at('relation')->value;
        $declared = is_string($name) && ! str_contains($name, '.') ? $context->resource->definition()->relation($name) : null;

        $context->reader->known($entry, $declared instanceof PolymorphicRelation ? [...self::KEYS, 'types' => true] : self::KEYS);

        $relation = $this->relation($entry, $context);
        $exists = $entry->at('exists');
        $type = $entry->at('type');

        $context->rules->addWhenPresent($exists, ['boolean:strict']);
        $context->rules->addWhenPresent($type, [Rule::in(['or', 'and'])]);
        $boolean = $this->filters->boolean($type, $context);

        if ($relation instanceof PolymorphicRelation) {
            $types = $this->types($entry, $relation, $context);

            return $types === null || ($exists->present && ! is_bool($exists->value)) ? $this->partial($context) : new MorphFilterGroup($relation, $exists->value !== false, $types, $boolean);
        }

        if ($relation === null) {
            return $this->partial($context);
        }

        $context->rules->reach(Check::onClass($relation->resource(), 'viewAny', $entry->path));
        $entries = $this->list($entry->at('nested'), $context);
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
            $resource->definition()->relations(),
        )));

        $context->rules->add($name->path, ['required', 'string', $names]);

        if (! is_string($name->value) || ! $names->accepts($name->value)) {
            return null;
        }

        return $resource->definition()->relation($name->value);
    }

    /** @return non-empty-array<string, FilterGroup>|null */
    private function types(Cursor $entry, Relation&PolymorphicRelation $relation, ValidationContext $context): ?array
    {
        $targets = $relation->targets();
        $types = $entry->at('types');
        $nested = $entry->at('nested');

        if ($targets->isPolymorphic() && $nested->present) {
            $context->rules->reject(Violation::severalTypes($nested->path));
        }

        if ($types->present) {
            $context->rules->add($types->path, ['array', 'min:1', ...($targets->isPolymorphic() ? [] : ['prohibits:'.$nested->path])]);
        }

        $lists = match (true) {
            ! $types->present => array_fill_keys($targets->types(), $targets->isPolymorphic() ? null : $nested),
            $context->reader->object($types) => $this->listed($types, $targets->types(), $context),
            default => null,
        };

        if ($lists === null || $lists === []) {
            return null;
        }

        $groups = [];

        foreach ($lists as $type => $list) {
            $target = $targets->forType($type);
            $entries = $list === null ? [] : $this->list($list, $context);

            if ($target === null || $entries === null) {
                return null;
            }

            $resource = $relation->resourceOf($target);
            $context->rules->reach(Check::onClass($resource, 'viewAny', $entry->path));
            $groups[$target->type] = $this->filters->group($entries, $context->for($resource)->deeper());
        }

        return $groups;
    }

    /**
     * @param  list<string>  $declared
     * @return array<string, Cursor>
     */
    private function listed(Cursor $types, array $declared, ValidationContext $context): array
    {
        $context->reader->known($types, array_fill_keys($declared, true));
        $lists = [];

        foreach (is_array($types->value) ? array_keys($types->value) : [] as $type) {
            if (in_array((string) $type, $declared, true)) {
                $lists[(string) $type] = $types->at($type);
            }
        }

        return $lists;
    }

    /** @return list<Cursor>|null */
    private function list(Cursor $list, ValidationContext $context): ?array
    {
        if ($context->filterDepth >= $context->limits->depths->filters) {
            $context->rules->add($list->path, ['prohibited']);

            return $context->reader->filled($list) ? null : [];
        }

        if (! $list->present) {
            return [];
        }

        $context->rules->add($list->path, ['array']);

        return $context->reader->entries($list, $context->limits->sizes->filters, true);
    }

    private function partial(ValidationContext $context): null
    {
        $context->rules->partial();

        return null;
    }
}
