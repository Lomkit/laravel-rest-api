<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;

class TitleLengths extends Instruction
{
    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        $query->selectRaw('length(posts.title) as title_length');
    }
}
