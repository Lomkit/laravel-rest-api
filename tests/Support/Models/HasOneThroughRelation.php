<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class HasOneThroughRelation extends BaseModel
{
    public function hasOneRelation() {
        return $this->belongsTo(HasOneRelation::class);
    }
}