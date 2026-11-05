<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Text\TextSearch;

class PlainTextSearchPostResource extends ContainsPostResource
{
    #[\Override]
    public function text(): TextSearch
    {
        return new PlainTextSearch;
    }
}
