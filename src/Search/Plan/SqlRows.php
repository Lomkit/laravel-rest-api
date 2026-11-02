<?php

namespace Lomkit\Rest\Search\Plan;

use LogicException;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\InstructionClause;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Clauses\ScopeClause;
use Lomkit\Rest\Query\Clauses\SortClause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Search\FieldSort;
use Lomkit\Rest\Search\RelevanceSort;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class SqlRows implements RowSource
{
    public function __construct(private ?ResolvedText $text) {}

    #[\Override]
    public function clauses(Resource $resource, SearchNode $search): array
    {
        return array_values(array_filter([
            $this->text($resource, $search),
            $search->filters->filters === [] ? null : FilterGroupClause::of($search->filters),
            TrashedClause::of($search->trashed),
            ...$this->order($resource, $search),
            ...array_map(ScopeClause::of(...), $search->scopes),
            ...array_map(InstructionClause::of(...), $search->instructions),
        ]));
    }

    private function text(Resource $resource, SearchNode $search): ?Clause
    {
        if ($search->text === null || $this->text === null) {
            return null;
        }

        if ($this->text->usesEngine()) {
            throw InvalidTextSearchException::runsOnEngine($resource::class);
        }

        return $this->text->clause();
    }

    /** @return list<Clause|null> */
    private function order(Resource $resource, SearchNode $search): array
    {
        if ($search->sorts === []) {
            return [$search->text === null ? null : $this->text?->relevance(), new DefaultOrderClause($resource)];
        }

        return array_map($this->sort(...), $search->sorts);
    }

    private function sort(SortNode $sort): Clause
    {
        return match (true) {
            $sort instanceof FieldSort => SortClause::of($sort),
            $sort instanceof RelevanceSort => $this->text?->relevance($sort->direction()) ?? throw InvalidFieldException::notSortable(RelevanceClause::FIELD),
            default => throw new LogicException($sort::class.' does not apply to a database query.'),
        };
    }
}
