<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;

class NoRelationshipAuthorizedModel extends Model
{
    protected $table = 'models';
}
