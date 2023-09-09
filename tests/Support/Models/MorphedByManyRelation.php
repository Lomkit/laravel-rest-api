<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class MorphedByManyRelation extends BaseModel
{
    public function model()
    {
        return $this->morphToMany(Model::class, 'inversable');
    }
}
