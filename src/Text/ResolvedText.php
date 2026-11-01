<?php

namespace Lomkit\Rest\Text;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Clauses\TextClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\EngineQuery;

final readonly class ResolvedText
{
    private function __construct(
        public TextBackend $backend,
        public ?TextQuery $query = null,
        private ?TextTarget $target = null,
        public ?TextCapabilities $capabilities = null,
        private ?QueryTextSearch $strategy = null,
        private ?EngineTextSearch $engine = null,
    ) {}

    public static function of(Resource $resource, RestRequest $request): self
    {
        $strategy = $resource->text($request);

        if ($strategy === null) {
            return new self(TextBackend::None);
        }

        $query = TextQuery::fromSearch($request->input('search.text'));

        return self::resolve($resource, $request, $strategy, $query?->hasTerms() ? $query : null);
    }

    public function usesEngine(): bool
    {
        return $this->engine !== null && $this->query !== null;
    }

    public function engineGrammar(): ?EngineGrammar
    {
        return $this->usesEngine() ? $this->capabilities?->engine : null;
    }

    public function ranks(): bool
    {
        return $this->strategy !== null && $this->query !== null && $this->capabilities?->relevance === true;
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

    public function engineQuery(RestRequest $request, ?Trashed $trashed = null): ?EngineQuery
    {
        if ($this->engine === null || $this->target === null || $this->query === null) {
            return null;
        }

        return $this->engine->query($this->query, $this->target, $request, $trashed);
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

    private static function resolve(Resource $resource, RestRequest $request, TextSearch $strategy, ?TextQuery $query): self
    {
        $target = TextTarget::of($resource, $request);

        return match (true) {
            $strategy instanceof QueryTextSearch => new self(TextBackend::Query, $query, $target, $strategy->capabilities($target), strategy: $strategy),
            $strategy instanceof EngineTextSearch => new self(TextBackend::Engine, $query, $target, self::engineCapabilities($resource, $strategy, $target), engine: $strategy),
            default => throw InvalidTextSearchException::unsupportedStrategy($resource::class, $strategy::class),
        };
    }

    private static function engineCapabilities(Resource $resource, EngineTextSearch $strategy, TextTarget $target): TextCapabilities
    {
        $capabilities = $strategy->capabilities($target);

        if ($capabilities->engine === null) {
            throw InvalidTextSearchException::missingEngineGrammar($resource::class, $strategy::class);
        }

        return $capabilities;
    }
}
