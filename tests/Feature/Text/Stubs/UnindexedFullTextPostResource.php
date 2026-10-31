<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\FullText;
use Lomkit\Rest\Text\TextSearch;

class UnindexedFullTextPostResource extends SearchablePostResource
{
    #[\Override]
    public function text(RestRequest $request): TextSearch
    {
        return FullText::make()
            ->index('title', 'summary')
            ->index('summary')
            ->otherwise(Contains::make());
    }
}
