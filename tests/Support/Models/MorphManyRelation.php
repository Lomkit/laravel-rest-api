<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class MorphManyRelation extends BaseModel
{
    public function model()
    {
        return $this->morphTo('morph_many_relation');
    }
}
