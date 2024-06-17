<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Laravel\Scout\Searchable;

class SearchableModel extends Model
{
    use Searchable;

    protected $table = 'models';
}
