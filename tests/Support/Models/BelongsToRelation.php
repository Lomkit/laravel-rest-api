<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class BelongsToRelation extends BaseModel
{
    public function model() {
        return $this->hasMany(Model::class);
    }
}