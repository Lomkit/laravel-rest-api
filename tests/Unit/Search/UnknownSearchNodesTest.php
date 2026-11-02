<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use LogicException;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Tests\Unit\Search\Stubs\UnknownFilterNode;
use Lomkit\Rest\Tests\Unit\Search\Stubs\UnknownSortNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnknownSearchNodesTest extends PackageTestCase
{
    #[Test]
    #[DataProvider('unknownNodes')]
    public function it_refuses_a_node_it_does_not_know(SearchNode $search, string $message): void
    {
        $this->expectExceptionObject(new LogicException($message));

        SearchContext::of($search);
    }

    /** @return array<string, array{SearchNode, string}> */
    public static function unknownNodes(): array
    {
        return [
            'a filter' => [
                new SearchNode(null, null, new FilterGroup([new UnknownFilterNode]), [], null, [], [], [], [], null, null, null),
                UnknownFilterNode::class.' is not a filter of a search.',
            ],
            'a sort' => [
                new SearchNode(null, null, new FilterGroup, [new UnknownSortNode], null, [], [], [], [], null, null, null),
                UnknownSortNode::class.' is not a sort of a search.',
            ],
        ];
    }
}
