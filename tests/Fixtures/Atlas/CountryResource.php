<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Http\Resource;

#[WithoutPerimeter]
class CountryResource extends Resource
{
    public static string $model = Country::class;
}
