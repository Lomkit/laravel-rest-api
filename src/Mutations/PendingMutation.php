<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class PendingMutation
{
    /** @param list<MutationNode> $entries */
    public function __construct(
        public array $entries,
        private RowSet $rows,
    ) {}

    public function row(Resource $resource, int|string $key): ?Model
    {
        return $this->rows->peek($resource, $key);
    }
}
