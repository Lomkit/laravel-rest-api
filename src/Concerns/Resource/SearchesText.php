<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Text\TextSearch;

trait SearchesText
{
    public function text(RestRequest $request): ?TextSearch
    {
        return null;
    }
}
