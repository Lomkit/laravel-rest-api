<?php

namespace Lomkit\Rest\Query;

use Lomkit\Rest\Search\Plan\PlannedPage;

interface SearchSource
{
    public function paginate(): PlannedPage;
}
