<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\TextQuery;

final readonly class SearchNode
{
    /**
     * @param  list<SortNode>  $sorts
     * @param  list<string>|null  $selects
     * @param  list<AggregateNode>  $aggregates
     * @param  list<IncludeNode>  $includes
     * @param  list<ScopeNode>  $scopes
     * @param  list<InstructionNode>  $instructions
     * @param  list<string>|null  $gates
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
    ) {}

    public static function empty(): self
    {
        return new self(null, null, new FilterGroup, [], null, [], [], [], [], null, null, null);
    }
}
