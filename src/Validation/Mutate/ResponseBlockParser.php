<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Search\SearchBodyParser;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class ResponseBlockParser
{
    private const array REFUSED = ['text', 'trashed', 'filters', 'scopes', 'sorts', 'instructions', 'limit', 'page'];

    public function __construct(
        private ParsedRules $rules,
        private NodeBudget $budget,
    ) {}

    public function parse(Cursor $response, Resource $resource): ?SearchNode
    {
        if (! $response->present) {
            return null;
        }

        $context = ValidationContext::root($resource, $this->budget, $this->rules)->related($resource);
        $this->rules->add($response->path, ['array']);

        if (! $context->reader->object($response)) {
            return null;
        }

        $context->reader->refuse($response, self::REFUSED);
        $this->rules->reach(Check::onClass($resource, 'viewAny', $response->path));

        return SearchBodyParser::block($response, $context)->node(null, null);
    }
}
