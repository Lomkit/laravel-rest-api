<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\SearchableModelResource;

class SearchableModelController extends Controller
{
    public static $resource = SearchableModelResource::class;
}
