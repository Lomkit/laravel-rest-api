<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class RowLimit
{
    private function __construct(
        private ParsedRules $rules,
        private int $max,
    ) {}

    public static function of(ValidationContext $context): self
    {
        return new self($context->rules, $context->limits->sizes->rows);
    }

    public function page(Cursor $search, SearchNode $node, ValidationContext $context): void
    {
        $limit = $search->at('limit');
        $rows = $node->limit ?? $context->resource->definition()
            ->limits()
            ->defaultLimit;

        $this->check($search, $node, $rows, $limit->present ? $limit->path : $search->path);
    }

    public function records(Cursor $response, SearchNode $node, int $records): void
    {
        $this->check($response, $node, $records, $response->path);
    }

    private function check(Cursor $level, SearchNode $node, int $rows, string $key): void
    {
        if ($this->rules->isPartial()) {
            return;
        }

        $nodes = RowTree::of($level, $node->includes);
        $total = RowNode::plus($rows, RowNode::sum($nodes, $rows));

        if ($total <= $this->max) {
            return;
        }

        $culprit = $rows > $this->max ? $key : $this->culprit($nodes, $rows, $rows)[1];

        $this->rules->reject(Violation::tooManyRows($culprit ?? $key, $total, $this->max));
    }

    /**
     * @param  list<RowNode>  $nodes
     * @return array{int, string|null}
     */
    private function culprit(array $nodes, int $parents, int $total): array
    {
        foreach ($nodes as $node) {
            $own = $node->own($parents);
            $total = RowNode::plus($total, $own);

            if ($total > $this->max) {
                return [$total, $node->key];
            }

            [$total, $key] = $this->culprit($node->heaviest($own), $own, $total);

            if ($key !== null) {
                return [$total, $key];
            }
        }

        return [$total, null];
    }
}
