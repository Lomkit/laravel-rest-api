<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class MorphToRelation extends BaseModel
{
    public function model()
    {
        return $this->morphOne(Model::class, 'morph_to_relation');
    }
}
