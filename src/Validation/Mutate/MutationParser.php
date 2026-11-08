<?php

namespace Lomkit\Rest\Validation\Mutate;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\LinkReplay;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\RowSet;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\PayloadReader;

final readonly class MutationParser
{
    public const array KEYS = ['operation', 'attributes', 'key', 'without_detaching', 'relations'];

    private function __construct(
        private ParsedRules $rules,
        private PayloadReader $reader,
        private Limits $limits,
        private MutationKeys $keys,
        private EntryRules $entryRules,
        private RelationMutationParser $relations,
        private LinkReplay $links,
    ) {}

    public static function of(MutateRequest $request, ParsedRules $rules, NodeBudget $budget, RowSet $rows, LinkReplay $links): self
    {
        $reader = new PayloadReader($rules, $budget);
        $limits = $request->context()->limits();
        $entryRules = new EntryRules($rules, new ResourceRules, $reader);

        return new self(
            $rules,
            $reader,
            $limits,
            new MutationKeys($rules, $reader, $limits->sizes->values, fn (Resource $resource): Builder => $request->keysQuery($resource), $rows),
            $entryRules,
            new RelationMutationParser($rules, $reader, $limits, $entryRules),
            $links,
        );
    }

    /** @return list<MutationNode> */
    public function parse(Cursor $mutate, Resource $resource): array
    {
        $this->rules->add($mutate->path, ['required', 'array']);

        if (! is_array($mutate->value)) {
            $this->rules->partial();

            return [];
        }

        $nodes = [];

        foreach ($this->reader->entries($mutate, $this->limits->sizes->mutate) ?? [] as $entry) {
            $node = $this->entry($entry, $resource, 0, null);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    public function entry(Cursor $entry, Resource $resource, int $depth, ?Relation $relation, ?string $type = null): ?MutationNode
    {
        $operation = MutationOperation::tryFromEntry($entry->value);
        $listed = $relation?->hasMultipleEntries() === true;

        if ($operation === MutationOperation::Create) {
            $this->rules->reach(Check::onClass($resource, 'create', $entry->path));
        }

        if (! $this->reader->object($entry)) {
            return null;
        }

        $this->relations->known($entry, $relation);
        $this->relations->constraints($entry, $resource, $operation);
        $this->rules->gate($entry, $entry->at('operation'));

        if ($operation?->requiresKey() === true) {
            $this->rules->gate($entry, $entry->at('key'));
        }

        $contextKey = $operation?->requiresKey() === true ? $this->keys->scalar($entry->at('key'), $resource) : null;
        $mutation = $operation === null ? null : new MutationContext($operation, $contextKey, $depth);
        $write = $operation?->writeOperation();

        if ($mutation !== null && $write !== null) {
            $this->entryRules->attributes($entry, $resource, $write, $mutation);
        }

        $operations = $relation?->acceptedOperations() ?? [MutationOperation::Create, MutationOperation::Update];
        $key = $this->ownKeys($entry, $resource, $operation, $operations, $listed);
        $relations = $this->relations->relations($entry, $resource, $depth, $this);
        $pivot = $this->relations->pivot($entry, $relation, $operation, $mutation);
        $attributes = $this->values($entry->at('attributes'));
        $withoutDetaching = $this->flag($entry->at('without_detaching'));

        if ($operations === []) {
            return null;
        }

        if ($operation === null || ! in_array($operation, $operations, true)) {
            $this->rules->partial();

            return null;
        }

        foreach (LinkRule::paths($entry, $operation, $relation) as $path) {
            $this->rules->add($path, [new LinkRule($this->links, $path)]);
        }

        return new MutationNode($operation, $key, $attributes, $pivot, $withoutDetaching, $relations, $depth, $type, $entry->path);
    }

    /**
     * @param  Closure(MutationOperation): bool  $applies
     * @return list<string>
     */
    public static function whenOperation(string $rule, Cursor $entry, Closure $applies): array
    {
        $operation = $entry->at('operation')->path;

        return array_values(array_map(
            fn (MutationOperation $candidate): string => $rule.':'.$operation.','.$candidate->value,
            array_filter(MutationOperation::cases(), $applies),
        ));
    }

    /**
     * @param  list<MutationOperation>  $operations
     * @return int|string|list<int|string>|null
     */
    private function ownKeys(Cursor $entry, Resource $resource, ?MutationOperation $operation, array $operations, bool $listed): int|string|array|null
    {
        $attributes = $entry->at('attributes');
        $write = $operation?->writeOperation();

        $this->rules->add($entry->at('operation')->path, ['required', $operations === [] ? Rule::enum(MutationOperation::class) : Rule::enum(MutationOperation::class)->only($operations)]);
        $this->rules->add($attributes->path, ['array', ...self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => $candidate->writeOperation() === null)]);

        if ($write !== null) {
            $this->entryRules->writable($attributes, $resource->definition()->fields(), $write);
        }

        $this->rules->add($entry->at('key')->path, [
            ...self::whenOperation('present_if', $entry, fn (MutationOperation $candidate): bool => $candidate->requiresKey()),
            ...self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => ! $candidate->requiresKey()),
        ]);
        $this->rules->addWhenPresent($entry->at('relations'), self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => ! $candidate->takesRelations()));
        $key = $this->keys->read($entry->at('key'), $resource, $operation, $listed);
        $this->rules->add($entry->at('without_detaching')->path, ['boolean', 'prohibited_unless:'.$entry->at('operation')->path.','.MutationOperation::Sync->value]);

        return $key;
    }

    /** @return array<array-key, mixed>|null */
    private function values(Cursor $value): ?array
    {
        if (is_array($value->value)) {
            return $value->value;
        }

        if ($value->present) {
            $this->rules->partial();
        }

        return null;
    }

    private function flag(Cursor $value): ?bool
    {
        $flag = match (true) {
            in_array($value->value, [true, 1, '1'], true) => true,
            in_array($value->value, [false, 0, '0'], true) => false,
            default => null,
        };

        if ($flag === null && $value->present) {
            $this->rules->partial();
        }

        return $flag;
    }
}
