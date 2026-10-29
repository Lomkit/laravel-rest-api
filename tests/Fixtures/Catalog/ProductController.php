<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Http\Controllers\Controller;

class ProductController extends Controller
{
    public static string $resource = ProductResource::class;
}
