<?php

namespace Lomkit\Rest\Actions;

use LogicException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\KeyList;

final readonly class OperationNode
{
    public function __construct(
        public Action $action,
        public InputValues $fields,
        public SearchNode|KeyList|null $target,
    ) {}

    public function keys(): KeyList
    {
        if (! $this->target instanceof KeyList) {
            throw new LogicException('The request has no resource keys.');
        }

        return $this->target;
    }

    public function search(): SearchNode
    {
        return $this->target instanceof SearchNode ? $this->target : SearchNode::empty();
    }
}
