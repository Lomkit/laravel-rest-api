<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PreparedTeamController extends Controller
{
    public static string $resource = PreparedTeamResource::class;
}
