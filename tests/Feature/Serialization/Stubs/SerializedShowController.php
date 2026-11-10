<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class SerializedShowController extends Controller
{
    public static string $resource = SerializedShowResource::class;
}
