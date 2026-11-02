<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class SearchBodyParser
{
    private const array GATES = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

    private const array SELECT_KEYS = ['field' => true];

    /**
     * @param  array<string, list<Cursor>|null>  $lists
     * @param  list<string>|null  $gates
     */
    private function __construct(
        private ValidationContext $context,
        private array $lists,
        private int $sorts,
        private ?array $gates,
        private ?int $limit,
        private ?int $page,
    ) {}

    public static function keys(Cursor $search, ValidationContext $context, bool $root): self
    {
        $resource = $context->resource;
        $grammar = $context->grammar;
        $limits = $context->limits;
        $limit = $search->at('limit');
        $page = $search->at('page');
        $sorts = $search->at('sorts');

        $context->rules->addWhenPresent($limit, ['integer', Rule::in($resource->getLimits($resource->context()->request)), ...$grammar->limitRules()]);

        if ($root) {
            $context->rules->addWhenPresent($page, ['integer', 'min:1', ...$grammar->pageRules($limit->value, $resource->defaultLimit)]);
        }

        $filters = self::list($search->at('filters'), ['array'], $limits->sizes->filters, $context, true);
        $gates = self::gates($search->at('gates'), $context);
        $lists = [
            'filters' => $filters,
            'scopes' => self::list($search->at('scopes'), ['array', ...$grammar->scopeRules()], $limits->sizes->scopes, $context),
            'sorts' => self::list($sorts, ['array'], $grammar->sortLimit($limits), $context),
            'selects' => self::list($search->at('selects'), ['array'], $limits->sizes->selects, $context),
            'aggregates' => self::list($search->at('aggregates'), ['array'], $limits->sizes->aggregates, $context),
            'includes' => self::includes($search->at('includes'), $context),
            'instructions' => self::list($search->at('instructions'), ['array'], $limits->sizes->instructions, $context),
        ];

        return new self(
            $context,
            $lists,
            is_array($sorts->value) ? count($sorts->value) : 0,
            $gates,
            self::integer($limit, $context),
            $root ? self::integer($page, $context) : null,
        );
    }

    public function node(?TextQuery $text, ?Trashed $trashed): SearchNode
    {
        $context = $this->context;

        return new SearchNode(
            filters: $context->grammar->filters($this->lists['filters'] ?? [], $context),
            scopes: $context->grammar->scopes($this->lists['scopes'] ?? [], $context),
            sorts: (new SortParser)->parse($this->lists['sorts'] ?? [], $this->sorts, $context),
            selects: $this->selects(),
            aggregates: (new AggregateParser)->parse($this->lists['aggregates'] ?? [], $context),
            includes: (new IncludeParser)->parse($this->lists['includes'] ?? [], $context),
            instructions: (new InstructionParser)->parse($this->lists['instructions'] ?? [], $context),
            text: $text,
            trashed: $trashed,
            gates: $this->gates,
            limit: $this->limit,
            page: $this->page,
        );
    }

    /** @return list<string>|null */
    private function selects(): ?array
    {
        $entries = $this->lists['selects'];

        if ($entries === null) {
            return null;
        }

        $context = $this->context;
        $resource = $context->resource;
        $fields = $context->vocabulary->of($resource, $context->grammar, 'selects', fn (): array => $resource
            ->getFields($resource->context()->request)
            ->selectable()
            ->names());
        $selects = [];

        foreach ($entries as $entry) {
            if (! $context->reader->object($entry)) {
                continue;
            }

            $context->reader->known($entry, self::SELECT_KEYS);
            $field = $entry->at('field');
            $context->rules->add($field->path, [$fields, 'required', 'string']);

            if (! is_string($field->value) || ! $fields->accepts($field->value)) {
                $context->rules->partial();

                continue;
            }

            $selects[] = $field->value;
        }

        return $selects;
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<Cursor>|null
     */
    private static function list(Cursor $list, array $rules, int $max, ValidationContext $context, bool $filters = false): ?array
    {
        $context->rules->addWhenPresent($list, $rules);

        return $context->reader->entries($list, $max, $filters);
    }

    /** @return list<Cursor>|null */
    private static function includes(Cursor $includes, ValidationContext $context): ?array
    {
        if ($context->includeDepth >= $context->limits->depths->includes) {
            $context->rules->add($includes->path, ['prohibited']);

            return null;
        }

        return self::list($includes, ['array'], $context->limits->sizes->includes, $context);
    }

    /** @return list<string>|null */
    private static function gates(Cursor $gates, ValidationContext $context): ?array
    {
        $context->rules->addWhenPresent($gates, ['array', Rule::in(self::GATES)]);
        $entries = $context->reader->entries($gates, $context->limits->sizes->gates);

        if (! $gates->present) {
            return null;
        }

        $known = [];

        foreach ($entries ?? [] as $entry) {
            if (is_string($entry->value) && in_array($entry->value, self::GATES, true)) {
                $known[] = $entry->value;
            }
        }

        if ($entries === null || count($known) !== count($entries)) {
            $context->rules->partial();
        }

        return $known;
    }

    private static function integer(Cursor $value, ValidationContext $context): ?int
    {
        if (! $value->present) {
            return null;
        }

        $integer = filter_var($value->value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            $context->rules->partial();

            return null;
        }

        return $integer;
    }
}
