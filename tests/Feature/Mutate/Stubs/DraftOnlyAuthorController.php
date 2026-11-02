<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class DraftOnlyAuthorController extends Controller
{
    public static string $resource = DraftOnlyAuthorResource::class;
}
