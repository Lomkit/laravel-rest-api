<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ReviewedTicketController extends Controller
{
    public static string $resource = ReviewedTicketResource::class;
}
