<?php

namespace Lomkit\Rest\Http\Requests\Traits;

use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Search\RowLimit;
use Lomkit\Rest\Validation\Search\SearchParser;
use Lomkit\Rest\Validation\ValidationContext;

trait ParsesSearch
{
    private ?SearchNode $parsedSearch = null;

    /** @return array<string, list<mixed>> */
    protected function searchRules(Resource $resource): array
    {
        $rules = $this->parsedRules();

        if ($this->parsedSearch === null) {
            $search = Cursor::of($this->all(), $rules->violations())->at('search');
            $context = ValidationContext::root($resource, $this->budget(), $rules);
            $this->parsedSearch = (new SearchParser)->parse($search, $context);

            if ($this->rendersPage()) {
                RowLimit::of($context)->page($search, $this->parsedSearch, $context);
            }
        }

        return $rules->rules();
    }

    protected function rendersPage(): bool
    {
        return true;
    }

    protected function searchNode(): SearchNode
    {
        $rules = $this->parsedRules();

        if ($this->parsedSearch === null || ! $rules->violations()->isEmpty() || $rules->isPartial() || ! $this->hasPassedValidation()) {
            throw new LogicException('The search was read before the request passed validation.');
        }

        return $this->parsedSearch;
    }
}
