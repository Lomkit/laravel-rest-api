<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Text\TextSearch;

trait SearchesText
{
    public function text(): ?TextSearch
    {
        return null;
    }
}
