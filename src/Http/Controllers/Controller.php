<?php

namespace Lomkit\Rest\Http\Controllers;

use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Http\Controllers\Traits\HasControllerHooks;
use Lomkit\Rest\Http\Resource;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use HasControllerHooks;
    use PerformsRestOperations;

    /**
     * The resource the entry corresponds to.
     *
     * @var class-string<Resource>
     */
    public static $resource;
}
