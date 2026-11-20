<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\Totals\TotalsNode;
use Lomkit\Rest\Text\TextQuery;

final readonly class SearchNode
{
    /**
     * @param  list<SortNode>  $sorts
     * @param  list<string>|null  $selects
     * @param  list<AggregateNode>  $aggregates
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @param  list<ScopeNode>  $scopes
     * @param  list<InstructionNode>  $instructions
     * @param  list<GateAbility>|null  $gates
     */
    public function __construct(
        public ?TextQuery $text,
        public ?Trashed $trashed,
        public FilterGroup $filters,
        public array $sorts,
        public ?array $selects,
        public array $aggregates,
        public array $includes,
        public array $scopes,
        public array $instructions,
        public ?array $gates,
        public ?int $limit,
        public ?int $page,
        public ?TotalsNode $totals = null,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, new FilterGroup, [], null, [], [], [], [], null, null, null);
    }

    /** @internal */
    public function withTotals(?TotalsNode $totals): self
    {
        return new self($this->text, $this->trashed, $this->filters, $this->sorts, $this->selects, $this->aggregates, $this->includes, $this->scopes, $this->instructions, $this->gates, $this->limit, $this->page, $totals);
    }
}
