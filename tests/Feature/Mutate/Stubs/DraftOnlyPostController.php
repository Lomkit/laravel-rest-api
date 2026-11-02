<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class DraftOnlyPostController extends Controller
{
    public static string $resource = DraftOnlyPostResource::class;
}
