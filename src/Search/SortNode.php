<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\SortDirection;

interface SortNode
{
    public function direction(): SortDirection;
}
