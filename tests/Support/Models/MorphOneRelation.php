<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class MorphOneRelation extends BaseModel
{
    public function model()
    {
        return $this->morphTo('morph_one_relation');
    }
}
