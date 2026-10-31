<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table('tag_ticket')]
class TagLink extends Pivot {}
