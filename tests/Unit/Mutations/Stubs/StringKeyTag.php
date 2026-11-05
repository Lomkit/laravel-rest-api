<?php

namespace Lomkit\Rest\Tests\Unit\Mutations\Stubs;

use Illuminate\Database\Eloquent\Casts\AsStringable;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;

class StringKeyTag extends Tag
{
    protected $table = 'tags';

    protected $casts = ['id' => AsStringable::class];
}
