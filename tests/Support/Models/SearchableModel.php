<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;

class SearchableModel extends Model
{
    use Searchable;
}
