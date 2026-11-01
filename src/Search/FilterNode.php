<?php

namespace Lomkit\Rest\Search;

interface FilterNode
{
    public function boolean(): string;
}
