<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\FullText;
use Lomkit\Rest\Text\TextSearch;

class FullTextPostResource extends SearchablePostResource
{
    #[\Override]
    public function text(): TextSearch
    {
        return FullText::make()
            ->index('title', 'summary')
            ->otherwise(Contains::make());
    }
}
