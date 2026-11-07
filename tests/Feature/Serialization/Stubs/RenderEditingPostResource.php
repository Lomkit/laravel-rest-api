<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class RenderEditingPostResource extends PostResource
{
    #[\Override]
    public function searching(SearchContext $search): void
    {
        $this->request()->merge(['search' => [
            'selects' => [['field' => 'id']],
            'gates' => ['create', 'view'],
            'includes' => [['relation' => 'author', 'alias' => 'writer']],
        ]]);
    }
}
