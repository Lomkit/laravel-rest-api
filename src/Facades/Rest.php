<?php

namespace Lomkit\Rest\Facades;

use Illuminate\Support\Facades\Facade;

class Rest extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor()
    {
        return 'lomkit-rest';
    }
}