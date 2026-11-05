<?php

namespace Lomkit\Rest\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Relations\Relation;

final readonly class MorphIncludeNode
{
    /**
     * @param  non-empty-list<Relation>  $relations
     * @param  non-empty-array<string, IncludeNode>  $branches
     */
    public function __construct(
        public string $relation,
        public array $relations,
        public Relation&PolymorphicRelation $morphTo,
        public ?string $alias,
        public array $branches,
    ) {}

    public function below(): ?self
    {
        $relations = array_slice($this->relations, 1);

        return $relations === [] ? null : new self(Str::after($this->relation, '.'), $relations, $this->morphTo, null, $this->branches);
    }
}
