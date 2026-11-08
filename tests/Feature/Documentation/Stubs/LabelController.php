<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Fixtures\Festival\LabelResource;

class LabelController extends Controller
{
    public static string $resource = LabelResource::class;
}
