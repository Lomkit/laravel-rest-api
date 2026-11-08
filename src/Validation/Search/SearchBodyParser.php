<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class SearchBodyParser
{
    private const array SELECT_KEYS = ['field' => true];

    /**
     * @param  array<string, list<Cursor>|null>  $lists
     * @param  list<GateAbility>|null  $gates
     */
    private function __construct(
        private ValidationContext $context,
        private array $lists,
        private int $sorts,
        private ?array $gates,
        private ?int $limit,
        private ?int $page,
    ) {}

    public static function keys(Cursor $search, ValidationContext $context, LevelBounds $bounds): self
    {
        $resource = $context->resource;
        $grammar = $context->grammar;
        $limits = $context->limits;
        $sizes = $resource->definition()->limits();
        $limit = $search->at('limit');
        $page = $search->at('page');
        $sorts = $search->at('sorts');

        if ($bounds->bounded()) {
            $context->rules->addWhenPresent($limit, ['integer', Rule::in($sizes->allowed), ...$grammar->limitRules()]);
        }

        if ($bounds->paged()) {
            $context->rules->addWhenPresent($page, ['integer', 'min:1', ...$grammar->pageRules($limit->value, $sizes->defaultLimit)]);
        }

        $filters = self::list($search->at('filters'), ['array'], $limits->sizes->filters, $context, true);
        $gates = (new GatesParser)->parse($search->at('gates'), $context);
        $lists = [
            'filters' => $filters,
            'scopes' => self::list($search->at('scopes'), ['array', ...$grammar->scopeRules()], $limits->sizes->scopes, $context),
            'sorts' => $bounds->bounded() ? self::list($sorts, ['array'], $grammar->sortLimit($limits), $context) : null,
            'selects' => self::list($search->at('selects'), ['array'], $limits->sizes->selects, $context),
            'aggregates' => self::list($search->at('aggregates'), ['array'], $limits->sizes->aggregates, $context),
            'includes' => self::includes($search->at('includes'), $context),
            'instructions' => self::list($search->at('instructions'), ['array'], $limits->sizes->instructions, $context),
        ];

        return new self(
            $context,
            $lists,
            $bounds->bounded() && is_array($sorts->value) ? count($sorts->value) : 0,
            $gates,
            $bounds->bounded() ? self::integer($limit, $context) : null,
            $bounds->paged() ? self::integer($page, $context) : null,
        );
    }

    public static function block(Cursor $block, ValidationContext $context): self
    {
        $sizes = $context->limits->sizes;
        $lists = [
            'selects' => self::list($block->at('selects'), ['array'], $sizes->selects, $context),
            'aggregates' => self::list($block->at('aggregates'), ['array'], $sizes->aggregates, $context),
            'includes' => self::includes($block->at('includes'), $context),
        ];

        return new self($context, $lists, 0, (new GatesParser(classLevel: false))->parse($block->at('gates'), $context), null, null);
    }

    public static function aggregate(Cursor $search, ValidationContext $context): self
    {
        $sizes = $context->limits->sizes;
        $lists = [
            'filters' => self::list($search->at('filters'), ['array'], $sizes->filters, $context, true),
            'scopes' => self::list($search->at('scopes'), ['array', ...$context->grammar->scopeRules()], $sizes->scopes, $context),
            'instructions' => self::list($search->at('instructions'), ['array'], $sizes->instructions, $context),
        ];

        return new self($context, $lists, 0, null, null, null);
    }

    public function node(?TextQuery $text, ?Trashed $trashed): SearchNode
    {
        $context = $this->context;
        $keys = RowKeys::of($context, $this->lists['includes'] ?? [], $this->lists['aggregates'] ?? []);
        $filters = $context->grammar->filters($this->lists['filters'] ?? [], $context);
        $scopes = $context->grammar->scopes($this->lists['scopes'] ?? [], $context);
        $aggregates = (new AggregateParser)->parse($this->lists['aggregates'] ?? [], $context, $keys);

        return new SearchNode(
            filters: $filters,
            scopes: $scopes,
            sorts: (new SortParser)->parse($this->lists['sorts'] ?? [], $this->sorts, $context->withAggregates($aggregates)),
            selects: $this->selects(),
            aggregates: $aggregates,
            includes: (new IncludeParser)->parse($this->lists['includes'] ?? [], $context, $keys),
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
        $entries = $this->lists['selects'] ?? null;

        if ($entries === null) {
            return null;
        }

        $context = $this->context;
        $resource = $context->resource;
        $fields = $context->vocabulary->of($resource, $context->grammar, 'selects', fn (): array => $resource
            ->definition()
            ->fields()
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
