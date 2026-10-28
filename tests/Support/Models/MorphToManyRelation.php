<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class MorphToManyRelation extends BaseModel
{
    public function models()
    {
        return $this->morphedByMany(Model::class, 'morphable');
    }
}
