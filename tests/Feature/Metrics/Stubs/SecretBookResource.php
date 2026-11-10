<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class SecretBookResource extends BookResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        if ($this->request()->user()?->getAuthIdentifier() === 1) {
            return $query;
        }

        return $query->where('books.title', '!=', 'Secret');
    }
}
