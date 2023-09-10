<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class MorphOneOfManyRelation extends BaseModel
{
    public function model()
    {
        return $this->morphTo('morph_one_of_many_relation');
    }
}
