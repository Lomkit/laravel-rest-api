<?php

namespace Lomkit\Rest\Tests\Concerns;

use Closure;
use Lomkit\Rest\Exceptions\SearchRunException;

trait CatchesLazyRefusals
{
    protected function lazyRefusal(Closure $lazy): SearchRunException
    {
        try {
            $lazy();
        } catch (SearchRunException $refusal) {
            return $refusal;
        }

        $this->fail('lazy() returned instead of throwing a SearchRunException.');
    }
}
