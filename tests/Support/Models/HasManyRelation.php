<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class HasManyRelation extends BaseModel
{
    public function model()
    {
        return $this->belongsTo(Model::class);
    }

    public function hasManyThroughRelation()
    {
        return $this->hasMany(HasManyThroughRelation::class, 'has_many_relation_id');
    }
}
