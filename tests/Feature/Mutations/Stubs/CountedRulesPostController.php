<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CountedRulesPostController extends Controller
{
    public static string $resource = CountedRulesPostResource::class;
}
