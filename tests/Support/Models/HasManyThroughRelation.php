<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class HasManyThroughRelation extends BaseModel
{
    public function hasManyRelation() {
        return $this->belongsTo(HasManyRelation::class);
    }
}