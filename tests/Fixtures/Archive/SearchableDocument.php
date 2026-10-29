<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Laravel\Scout\Searchable;

#[Table('documents')]
#[UseFactory(SearchableDocumentFactory::class)]
#[UsePolicy(OpenArchivePolicy::class)]
class SearchableDocument extends Document
{
    use Searchable;

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return ['title' => $this->title];
    }
}
