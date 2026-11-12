<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Lomkit\Rest\Relations\Relation;

final readonly class SyncClaim
{
    /**
     * @param  HasOneOrMany<Model, Model, mixed>  $children
     * @param  list<mixed>  $accounted
     */
    public function __construct(
        public Relation $relation,
        public HasOneOrMany $children,
        public string $path,
        public array $accounted,
    ) {}

    public function parent(): Model
    {
        return $this->children->getParent();
    }
}
