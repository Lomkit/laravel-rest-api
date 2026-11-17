<?php

namespace Lomkit\Rest\Text;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\NarrowedFields;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Clauses\TextClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\Run\PayloadSearchRequest;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Validation\Search\SearchGrammar;
use Lomkit\Rest\Validation\Search\SqlSearchGrammar;

final readonly class ResolvedText
{
    private function __construct(
        public TextBackend $backend,
        public ?TextQuery $query = null,
        private ?TextTarget $target = null,
        public ?TextCapabilities $capabilities = null,
        private ?QueryTextSearch $strategy = null,
        private ?EngineTextSearch $engine = null,
        private bool $listing = false,
        private bool $streams = false,
    ) {}

    public static function of(Resource $resource, RestRequest $request): self
    {
        $strategy = $resource->text();

        if ($strategy === null) {
            return new self(TextBackend::None);
        }

        $query = TextQuery::fromSearch($request->input('search.text'));
        $terms = $query?->hasTerms() ? $query : null;
        $streams = $request instanceof PayloadSearchRequest && $request->streams();

        return self::resolve($resource, $strategy, $terms, $terms === null && ! $streams && self::listsOnEngine($strategy, $request), $streams);
    }

    /** @internal */
    public function narrowedBy(NarrowedFields $narrowing): self
    {
        $engine = $this->capabilities?->engine;

        if ($this->capabilities === null || $engine === null || $narrowing->isEmpty()) {
            return $this;
        }

        $narrowed = $engine->without($narrowing->unfilterableNames(), $narrowing->unsortableNames());

        return new self($this->backend, $this->query, $this->target, $this->capabilities->withEngine($narrowed), $this->strategy, $this->engine, $this->listing, $this->streams);
    }

    public function usesEngine(): bool
    {
        return $this->engine !== null && ($this->query !== null || $this->listing);
    }

    public function engineGrammar(): ?EngineGrammar
    {
        return $this->usesEngine() ? $this->capabilities?->engine : null;
    }

    public function ranks(): bool
    {
        return $this->strategy !== null && $this->query !== null && $this->capabilities?->relevance === true;
    }

    public function grammar(): SearchGrammar
    {
        $engine = $this->engineGrammar();

        if ($engine === null || $this->capabilities === null) {
            return new SqlSearchGrammar($this->capabilities, $this->ranks(), $this->streams);
        }

        return $engine->searchGrammar($this->capabilities, $this->listing);
    }

    public function sqlGrammar(): SearchGrammar
    {
        return new SqlSearchGrammar($this->strategy === null ? null : $this->capabilities, $this->ranks());
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

    public function engineQuery(?Trashed $trashed = null): ?EngineQuery
    {
        if ($this->engine === null || $this->target === null) {
            return null;
        }

        if ($this->query !== null) {
            return $this->engine->query($this->query, $this->target, $trashed);
        }

        return $this->listing && $this->engine instanceof ListingTextSearch ? $this->engine->list($this->target, $trashed) : null;
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

    private static function listsOnEngine(TextSearch $strategy, RestRequest $request): bool
    {
        if (! $strategy instanceof ListingTextSearch || ! $strategy->servingLists()) {
            return false;
        }

        return ! $strategy->databaseListsAllowed() || $request->input('search.source') !== ListSource::Database->value;
    }

    private static function resolve(Resource $resource, TextSearch $strategy, ?TextQuery $query, bool $listing, bool $streams): self
    {
        $target = TextTarget::of($resource);

        return match (true) {
            $strategy instanceof QueryTextSearch => new self(TextBackend::Query, $query, $target, $strategy->capabilities($target), strategy: $strategy),
            $strategy instanceof EngineTextSearch => new self(TextBackend::Engine, $query, $target, self::engineCapabilities($resource, $strategy, $target), engine: $strategy, listing: $listing, streams: $streams),
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
