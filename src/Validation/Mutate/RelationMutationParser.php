<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\KeyPath;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\PayloadReader;
use Lomkit\Rest\Validation\Violation;

final readonly class RelationMutationParser
{
    public function __construct(
        private ParsedRules $rules,
        private PayloadReader $reader,
        private Limits $limits,
        private RestRequest $request,
        private EntryRules $entryRules,
    ) {}

    public function known(Cursor $entry, ?Relation $relation): void
    {
        $keys = match (true) {
            $relation instanceof PivotRelation => [...MutationParser::KEYS, 'pivot'],
            $relation === null => MutationParser::KEYS,
            default => [...MutationParser::KEYS, ...$relation->entryRule()->keys()],
        };

        $this->reader->known($entry, array_fill_keys($keys, true));
    }

    public function constraints(Cursor $entry, Resource $resource, ?MutationOperation $operation): void
    {
        if ($operation !== MutationOperation::Create && $operation !== MutationOperation::Update) {
            return;
        }

        $relations = $entry->at('relations');

        foreach ($resource->getRelations() as $relation) {
            $constraint = $this->constraint($relation, $operation);

            if ($constraint !== null) {
                $this->rules->add($relations->at($relation->relation)->path, [$constraint]);
            }
        }
    }

    /** @return array<string, MutationNode|list<MutationNode>>|null */
    public function relations(Cursor $entry, Resource $resource, int $depth, MutationParser $parser): ?array
    {
        $relations = $entry->at('relations');

        if ($depth >= $this->limits->depths->mutations) {
            $this->rules->add($relations->path, ['prohibited']);

            if ($this->reader->filled($relations)) {
                $this->rules->partial();
            }

            return null;
        }

        $this->rules->addWhenPresent($relations, ['array']);

        if (! is_array($relations->value)) {
            $this->reader->object($relations);

            return null;
        }

        $declared = [];

        foreach ($resource->getRelations() as $relation) {
            $declared[$relation->relation] = $relation;
        }

        $nodes = [];

        foreach (array_keys($relations->value) as $name) {
            if (! isset($declared[$name])) {
                $this->undeclared($relations, $name);

                continue;
            }

            $node = $this->relation($relations->at($name), $declared[$name], $depth, $parser);

            if ($node !== null) {
                $nodes[$declared[$name]->relation] = $node;
            }
        }

        return $nodes;
    }

    /** @return array<array-key, mixed>|null */
    public function pivot(Cursor $entry, ?Relation $relation, ?MutationOperation $operation, ?MutationContext $mutation): ?array
    {
        if (! $relation instanceof PivotRelation) {
            return null;
        }

        $this->entryRules->pivot($entry, $relation, $operation?->pivotWriteOperation(), $mutation);
        $pivot = $entry->at('pivot')->value;

        return is_array($pivot) ? $pivot : null;
    }

    /** @return MutationNode|list<MutationNode>|null */
    private function relation(Cursor $value, Relation $relation, int $depth, MutationParser $parser): MutationNode|array|null
    {
        if (! $this->reader->object($value)) {
            return null;
        }

        if (! $relation->hasMultipleEntries()) {
            $target = $relation->entryRule()->target($value, $this->rules);

            if ($target === null) {
                return null;
            }

            $this->relationRules($value, $relation, $target->resource);

            return $parser->entry($value, $target->resource, $depth + 1, $relation);
        }

        $resource = $relation->resource();

        $this->rules->add($value->path, ['array']);
        $this->relationRules($value, $relation, $resource);

        $nodes = [];

        foreach ($this->reader->entries($value, $this->limits->sizes->relationEntries) ?? [] as $item) {
            $node = $parser->entry($item, $resource, $depth + 1, $relation);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function undeclared(Cursor $relations, int|string $name): void
    {
        $this->rules->reject(Violation::unnamedKey($relations->path, (string) $name));

        if (! KeyPath::canName($name)) {
            return;
        }

        $value = $relations->at($name);

        $this->reader->object($value);
    }

    private function relationRules(Cursor $value, Relation $relation, Resource $resource): void
    {
        foreach ($relation->rules($resource, $value->path) as $key => $list) {
            $this->rules->add($key, WriteRules::listOf($list));
        }
    }

    private function constraint(Relation $relation, MutationOperation $operation): ?string
    {
        $creation = $operation === MutationOperation::Create;
        $prohibited = $creation ? $relation->isProhibitedOnCreation($this->request) : $relation->isProhibitedOnUpdate($this->request);
        $required = $creation ? $relation->isRequiredOnCreation($this->request) : $relation->isRequiredOnUpdate($this->request);

        return match (true) {
            $required => 'required',
            $prohibited => 'prohibited',
            default => null,
        };
    }
}
