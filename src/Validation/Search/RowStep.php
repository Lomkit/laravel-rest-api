<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\MorphIncludeNode;
use Lomkit\Rest\Validation\Cursor;

final readonly class RowStep
{
    private function __construct(
        public IncludeNode|MorphIncludeNode $include,
        public int $from,
        public Cursor $entry,
    ) {}

    /**
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @return list<self>
     */
    public static function of(Cursor $level, array $includes): array
    {
        $list = $level->at('includes');
        $steps = [];

        foreach ($includes as $index => $include) {
            $steps[] = new self($include, 0, $list->item($index));
        }

        return $steps;
    }

    public function segment(): string
    {
        return explode('.', $this->include->relation)[$this->from] ?? $this->include->relation;
    }

    public function relation(): Relation
    {
        return $this->include->relations[$this->from] ?? $this->include->relations[array_key_last($this->include->relations)];
    }

    public function isLast(): bool
    {
        return $this->from >= count($this->include->relations) - 1;
    }

    public function next(): self
    {
        return new self($this->include, $this->from + 1, $this->entry);
    }
}
