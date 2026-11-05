<?php

namespace Lomkit\Rest\Tests\Unit\Mutations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class SubclassingTagResource extends TagResource
{
    public static string $model = SubclassingTag::class;
}
