<?php

namespace Lomkit\Rest\Text;

use Closure;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Clauses\TextClause;

final readonly class ResolvedText
{
    private function __construct(
        public TextBackend $backend,
        public ?TextQuery $query = null,
        private ?TextSearch $strategy = null,
        private ?TextTarget $target = null,
        public ?TextCapabilities $capabilities = null,
    ) {}

    public static function of(Resource $resource, RestRequest $request): self
    {
        $strategy = $resource->text($request);
        $query = TextQuery::fromSearch($request->input('search.text'));

        if ($strategy !== null) {
            $target = TextTarget::of($resource, $request);

            return new self(TextBackend::Strategy, $query?->hasTerms() ? $query : null, $strategy, $target, $strategy->capabilities($target));
        }

        if ($resource->isModelSearchable() && $request->endpoint() !== Endpoint::Operate) {
            return new self(TextBackend::Scout, $query);
        }

        return new self(TextBackend::None);
    }

    public function usesScout(): bool
    {
        return $this->backend === TextBackend::Scout && $this->query !== null;
    }

    public function ranks(): bool
    {
        return $this->query !== null && $this->capabilities?->relevance === true;
    }

    public function clause(): ?TextClause
    {
        if ($this->strategy === null || $this->target === null || $this->query === null) {
            return null;
        }

        return new TextClause($this->strategy, $this->query, $this->target);
    }

    public function relevance(SortDirection $direction = SortDirection::Desc): ?RelevanceClause
    {
        if ($this->strategy === null || $this->target === null || $this->query === null || ! $this->ranks()) {
            return null;
        }

        return new RelevanceClause($this->strategy, $this->query, $this->target, $direction);
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $query
     * @return TResult
     */
    public function run(Closure $query): mixed
    {
        if ($this->strategy === null || $this->target === null) {
            return $query();
        }

        return $this->strategy->run($query, $this->target);
    }
}
