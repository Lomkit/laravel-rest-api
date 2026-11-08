<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ReadOnlyTicketsTeamController extends Controller
{
    public static string $resource = ReadOnlyTicketsTeamResource::class;
}
