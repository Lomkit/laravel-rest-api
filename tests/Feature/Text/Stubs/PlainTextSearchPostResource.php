<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Text\TextSearch;

class PlainTextSearchPostResource extends ContainsPostResource
{
    #[\Override]
    public function text(RestRequest $request): TextSearch
    {
        return new PlainTextSearch;
    }
}
