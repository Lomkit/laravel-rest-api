<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class FieldCountingPostController extends Controller
{
    public static string $resource = FieldCountingPostResource::class;
}
