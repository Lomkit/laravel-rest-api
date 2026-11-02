<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Search\SearchParser;
use Lomkit\Rest\Validation\ValidationContext;

trait ParsesSearchNodes
{
    /** @param array<string, mixed> $search */
    private function parse(Resource $resource, array $search): SearchNode
    {
        $rules = new ParsedRules;
        $node = (new SearchParser)->parse(
            Cursor::of(['search' => $search], $rules->violations())->at('search'),
            ValidationContext::root($resource, NodeBudget::of($resource->context()->limits()), $rules),
        );

        $this->assertTrue($rules->violations()->isEmpty());
        $this->assertFalse($rules->isPartial());

        return $node;
    }
}
