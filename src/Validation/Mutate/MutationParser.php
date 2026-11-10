<?php

namespace Lomkit\Rest\Validation\Mutate;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\LinkReplay;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\RowSet;
use Lomkit\Rest\Mutations\VersionClaims;
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
        private EntryKeyRules $keyRules,
    ) {}

    public static function of(MutateRequest $request, ParsedRules $rules, NodeBudget $budget, RowSet $rows, LinkReplay $links, VersionClaims $versions): self
    {
        $reader = new PayloadReader($rules, $budget);
        $limits = $request->context()->limits();
        $entryRules = new EntryRules($rules, new ResourceRules, $reader);
        $keys = new MutationKeys($rules, $reader, $limits->sizes->values, fn (Resource $resource): Builder => $request->keysQuery($resource), $rows);

        return new self(
            $rules,
            $reader,
            $limits,
            $keys,
            $entryRules,
            new RelationMutationParser($rules, $reader, $limits, $entryRules),
            $links,
            new EntryKeyRules($rules, $keys, $entryRules, $versions),
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
            $node = $this->entry($entry, $resource, 0, null, null, 1);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    public function entry(Cursor $entry, Resource $resource, int $depth, ?Relation $relation, ?string $type, int $runs): ?MutationNode
    {
        $operation = MutationOperation::tryFromEntry($entry->value);
        $listed = $relation?->hasMultipleEntries() === true;

        if ($operation === MutationOperation::Create) {
            $this->rules->reach(Check::onClass($resource, 'create', $entry->path));
        }

        if (! $this->reader->object($entry)) {
            return null;
        }

        $this->relations->known($entry, $relation, $resource->definition()->version() !== null);
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
        $key = $this->keyRules->apply($entry, $resource, $operation, $operations, $listed, $runs);
        $relations = $this->relations->relations($entry, $resource, $depth, $this, $runs * self::rows($key));
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

        if ($operation === MutationOperation::Sync && $relation?->syncRequiresWithoutDetaching() === true) {
            $this->keyRules->keepLinks($entry->at('without_detaching'), $withoutDetaching);
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
        return EntryKeyRules::whenOperation($rule, $entry, $applies);
    }

    /** @param int|string|list<int|string>|null $key */
    private static function rows(int|string|array|null $key): int
    {
        return is_array($key) ? max(1, count($key)) : 1;
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
