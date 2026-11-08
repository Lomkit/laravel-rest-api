<?php

namespace Lomkit\Rest\Search\Run;

use Generator;
use Illuminate\Support\LazyCollection;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\EngineSearch;
use Lomkit\Rest\Query\SearchSource;
use Lomkit\Rest\Query\SqlSearch;
use Lomkit\Rest\Search\Plan\Columns\StrictColumns;
use Lomkit\Rest\Search\Plan\IncludeOverflow;
use Lomkit\Rest\Search\Plan\PlannedRows;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class SearchRun
{
    private function __construct(
        private Resource $resource,
        private SearchNode $search,
    ) {}

    public static function of(Resource $resource, SearchNode $search): self
    {
        return new self($resource, $search);
    }

    public function page(): SearchedPage
    {
        $context = SearchContext::of($this->search);
        $this->resource->searching($context);

        $page = $this->source()->paginate();
        $overflow = IncludeOverflow::trim($page->plan, $page->rows->items());
        $strict = StrictColumns::of($page->plan);

        $strict->run(fn () => $this->resource->searched($page->rows));
        $meta = $strict->run(fn (): array => SearchMeta::of($this->resource, $this->search)->compute($context, $page->rows));

        return new SearchedPage($page->plan, $page->rows, $overflow, $meta);
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    public function lazy(int $chunk, ?int $max): LazyCollection
    {
        $text = $this->text();

        if ($text->usesEngine()) {
            throw SearchRunException::engineWindow($this->resource::class, $text->engineGrammar()?->window);
        }

        $this->resource->searching(SearchContext::of($this->search));

        $rows = (new SqlSearch($this->resource, $text, $this->search))->chunks($chunk, $max);

        return LazyCollection::make(fn (): Generator => self::rendered($rows));
    }

    /** @return Generator<int, array<string, mixed>> */
    private static function rendered(PlannedRows $rows): Generator
    {
        foreach ($rows->chunks->chunks() as $chunk) {
            $models = $chunk->all();
            $rendered = $rows->plan->resource::newResponse()
                ->plan($rows->plan)
                ->overflow(IncludeOverflow::trim($rows->plan, $models))
                ->rows($models);

            foreach ($rendered as $row) {
                yield $row;
            }
        }
    }

    private function source(): SearchSource
    {
        $text = $this->text();

        if ($text->usesEngine()) {
            return new EngineSearch($this->resource, $text, $this->search);
        }

        return new SqlSearch($this->resource, $text, $this->search);
    }

    private function text(): ResolvedText
    {
        return $this->resource
            ->context()
            ->text($this->resource);
    }
}
