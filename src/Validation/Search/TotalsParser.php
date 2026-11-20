<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\MeasureNode;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Search\Totals\TotalsNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

/** @internal */
final readonly class TotalsParser
{
    private const string EMPTY = 'rest::validation.empty_totals';

    private const array KEYS = ['measures' => true, 'groups' => true];

    private const array MEASURE_KEYS = ['type' => true, 'field' => true];

    private const array GROUP_KEYS = ['field' => true, 'limit' => true];

    public function parse(Cursor $totals, ValidationContext $context): ?TotalsNode
    {
        if (! $totals->present) {
            return null;
        }

        $context->rules->add($totals->path, ['array']);

        if (! $context->reader->object($totals)) {
            return null;
        }

        $vocabulary = $context->grammar->totals($context);

        if ($vocabulary->refusal !== null) {
            $context->rules->reject(new Violation($totals->path, $vocabulary->refusal));

            return null;
        }

        $context->reader->known($totals, self::KEYS);

        return $this->node($totals, $vocabulary, $context);
    }

    private function node(Cursor $totals, TotalsVocabulary $vocabulary, ValidationContext $context): ?TotalsNode
    {
        $sizes = $context->limits->sizes;
        $measures = $totals->at('measures');
        $groups = $totals->at('groups');

        if (! $context->reader->filled($measures) && ! $context->reader->filled($groups)) {
            $context->rules->reject(new Violation($totals->path, self::EMPTY));

            return null;
        }

        $context->rules->addWhenPresent($measures, ['array']);
        $context->rules->addWhenPresent($groups, ['array']);

        return new TotalsNode(
            $this->measures($context->reader->entries($measures, $sizes->measures) ?? [], $vocabulary, $context),
            $this->groups($context->reader->entries($groups, $sizes->totalsGroups) ?? [], $vocabulary, $context),
        );
    }

    /**
     * @param  list<Cursor>  $entries
     * @return list<MeasureNode>
     */
    private function measures(array $entries, TotalsVocabulary $vocabulary, ValidationContext $context): array
    {
        $fields = new OneOf(array_keys($vocabulary->measures));
        $measures = [];

        foreach ($entries as $entry) {
            if (! $context->reader->object($entry)) {
                continue;
            }

            $context->reader->known($entry, self::MEASURE_KEYS);
            $type = $entry->at('type');
            $field = $entry->at('field');

            $context->rules->gate($entry, $type, $field);
            $context->rules->add($type->path, ['required', Rule::enum(MeasureType::class)]);
            $context->rules->add($field->path, [$fields, 'required', 'string']);

            $parsed = is_string($type->value) ? MeasureType::tryFrom($type->value) : null;
            $measured = is_string($field->value) ? $vocabulary->measures[$field->value] ?? null : null;

            if ($parsed === null || $measured === null) {
                $context->rules->partial();

                continue;
            }

            if (! $vocabulary->measures($measured->name, $parsed)) {
                $context->rules->reject(new Violation($type->path, $vocabulary->uncomputed));

                continue;
            }

            $measure = new MeasureNode($parsed, $measured);

            if (isset($measures[$measure->key()])) {
                $context->rules->reject(Violation::repeated($entry->path));

                continue;
            }

            $measures[$measure->key()] = $measure;
        }

        return array_values($measures);
    }

    /**
     * @param  list<Cursor>  $entries
     * @return list<GroupNode>
     */
    private function groups(array $entries, TotalsVocabulary $vocabulary, ValidationContext $context): array
    {
        $fields = new OneOf(array_keys($vocabulary->groups));
        $max = min($context->limits->sizes->groups, $vocabulary->limit ?? PHP_INT_MAX);
        $groups = [];

        foreach ($entries as $entry) {
            if (! $context->reader->object($entry)) {
                continue;
            }

            $context->reader->known($entry, self::GROUP_KEYS);
            $field = $entry->at('field');
            $limit = $entry->at('limit');

            $context->rules->gate($entry, $field);
            $context->rules->add($field->path, [$fields, 'required', 'string']);
            $context->rules->addWhenPresent($limit, ['integer', 'min:1', 'max:'.$max]);

            $grouped = is_string($field->value) ? $vocabulary->groups[$field->value] ?? null : null;
            $size = $limit->present ? filter_var($limit->value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]) : $max;

            if ($grouped === null || $size === false) {
                $context->rules->partial();

                continue;
            }

            if (isset($groups[$grouped->name])) {
                $context->rules->reject(Violation::repeated($entry->path));

                continue;
            }

            $groups[$grouped->name] = new GroupNode($grouped, $size);
        }

        return array_values($groups);
    }
}
