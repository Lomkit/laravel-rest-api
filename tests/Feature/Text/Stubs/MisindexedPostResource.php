<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\FullText;
use Lomkit\Rest\Text\TextSearch;

class MisindexedPostResource extends SearchablePostResource
{
    #[\Override]
    public function text(): TextSearch
    {
        return FullText::make()
            ->index('title', 'status')
            ->otherwise(Contains::make());
    }
}
