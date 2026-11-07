<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class InputEditingPostResource extends PostResource
{
    #[\Override]
    public function searching(SearchContext $search): void
    {
        $this->request()->merge(['search' => [
            'filters' => [['field' => 'id', 'value' => 2]],
            'sorts' => [['field' => 'id', 'direction' => 'desc']],
            'scopes' => [['name' => 'popular']],
            'limit' => 10,
            'page' => 2,
        ]]);
    }
}
