<?php

namespace Lomkit\Rest\Search\Run;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\EngineSearch;
use Lomkit\Rest\Query\SearchSource;
use Lomkit\Rest\Query\SqlSearch;
use Lomkit\Rest\Search\Plan\Columns\StrictColumns;
use Lomkit\Rest\Search\Plan\IncludeOverflow;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SearchNode;

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
        $this->resource->searching(SearchContext::of($this->search));

        $page = $this->source()->paginate();
        $overflow = IncludeOverflow::trim($page->plan, $page->rows->items());

        StrictColumns::of($page->plan)->run(fn () => $this->resource->searched($page->rows));

        return new SearchedPage($page->plan, $page->rows, $overflow);
    }

    private function source(): SearchSource
    {
        $text = $this->resource
            ->context()
            ->text($this->resource);

        if ($text->usesEngine()) {
            return new EngineSearch($this->resource, $text, $this->search);
        }

        return new SqlSearch($this->resource, $text, $this->search);
    }
}
