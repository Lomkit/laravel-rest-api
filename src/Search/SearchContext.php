<?php

namespace Lomkit\Rest\Search;

use LogicException;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineSort;
use Lomkit\Rest\Text\TextQuery;

final readonly class SearchContext
{
    /**
     * @param  list<FlatFilter>  $filters
     * @param  list<string>  $sorts
     * @param  list<string>  $scopes
     * @param  list<string>  $includes
     */
    private function __construct(
        public array $filters,
        private array $sorts,
        private array $scopes,
        private array $includes,
        public ?TextQuery $text,
        public ?Trashed $trashed,
    ) {}

    public static function of(SearchNode $search): self
    {
        return new self(
            (new FilterFlattener)->flatten($search->filters),
            array_map(self::sort(...), $search->sorts),
            array_map(static fn (ScopeNode $node): string => $node->scope->name, $search->scopes),
            array_map(static fn (IncludeNode|MorphIncludeNode $node): string => $node->relation, $search->includes),
            $search->text,
            $search->trashed,
        );
    }

    public function filtersOn(string $path): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->path === $path) {
                return true;
            }
        }

        return false;
    }

    public function sortsOn(string $field): bool
    {
        return in_array($field, $this->sorts, true);
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string> */
    public function includes(): array
    {
        return $this->includes;
    }

    private static function sort(SortNode $sort): string
    {
        return match (true) {
            $sort instanceof FieldSort => $sort->field->name,
            $sort instanceof EngineSort => $sort->attribute,
            $sort instanceof RelevanceSort => RelevanceClause::FIELD,
            $sort instanceof RelationSort => $sort->path(),
            $sort instanceof AggregateSort => $sort->key->value,
            default => throw new LogicException($sort::class.' is not a sort of a search.'),
        };
    }
}
