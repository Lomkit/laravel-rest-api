<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class SharedDocumentResource extends DocumentResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('documents.title', 'Owned')->orWhere('documents.title', 'Shared');
    }
}
