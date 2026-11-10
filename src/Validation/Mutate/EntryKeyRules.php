<?php

namespace Lomkit\Rest\Validation\Mutate;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\VersionClaims;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Violation;

final readonly class EntryKeyRules
{
    public function __construct(
        private ParsedRules $rules,
        private MutationKeys $keys,
        private EntryRules $entryRules,
        private VersionClaims $versions,
    ) {}

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
    public function apply(Cursor $entry, Resource $resource, ?Relation $relation, ?MutationOperation $operation, array $operations, bool $listed, int $runs): int|string|array|null
    {
        $attributes = $entry->at('attributes');
        $write = $operation?->writeOperation();
        $writesRow = fn (MutationOperation $candidate): bool => $operations === [] || $relation === null || $relation->writesRelatedRows($candidate);

        $this->rules->add($entry->at('operation')->path, ['required', $operations === [] ? Rule::enum(MutationOperation::class) : Rule::enum(MutationOperation::class)->only($operations)]);
        $this->rules->add($attributes->path, ['array', ...self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => $candidate->writeOperation() === null || ! $writesRow($candidate))]);

        if ($write !== null && $writesRow($operation)) {
            $this->entryRules->writable($attributes, $resource->definition()->fields(), $write);
        }

        $this->rules->add($entry->at('key')->path, [
            ...self::whenOperation('present_if', $entry, fn (MutationOperation $candidate): bool => $candidate->requiresKey()),
            ...self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => ! $candidate->requiresKey()),
        ]);
        $this->rules->addWhenPresent($entry->at('relations'), self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => ! $candidate->takesRelations() || ! $writesRow($candidate)));
        $key = $this->keys->read($entry->at('key'), $resource, $operation, $listed, $runs);
        $this->rules->add($entry->at('without_detaching')->path, ['boolean', 'prohibited_unless:'.$entry->at('operation')->path.','.MutationOperation::Sync->value]);
        $this->version($entry, $resource, $operation, $key);

        return $key;
    }

    public function keepLinks(Cursor $value, ?bool $withoutDetaching): void
    {
        if ($withoutDetaching === true || ($value->present && $withoutDetaching === null)) {
            return;
        }

        $this->rules->reject(Violation::keptLinks($value->path));
    }

    /** @param int|string|list<int|string>|null $key */
    private function version(Cursor $entry, Resource $resource, ?MutationOperation $operation, int|string|array|null $key): void
    {
        $version = $resource->definition()->version();

        if ($version === null) {
            return;
        }

        $value = $entry->at('version');
        $this->rules->add($value->path, ['string', ...self::whenOperation('prohibited_if', $entry, fn (MutationOperation $candidate): bool => $candidate !== MutationOperation::Update)]);

        if ($operation === MutationOperation::Update && $key !== null && is_string($value->value)) {
            $this->versions->claim($resource, $version, $entry->path, $key, $value->value);
        }
    }
}
